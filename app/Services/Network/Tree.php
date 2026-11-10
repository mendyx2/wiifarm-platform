<?php

namespace App\Services\Network;

use App\Models\NetworkAlert;
use App\Models\TreePosition;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The binary tree (specification section 3): finding a free slot, claiming
 * it, and walking up to a member's uplines.
 */
class Tree
{
    /** Attempts before giving up when other members keep taking the slot found. */
    private const CLAIM_ATTEMPTS = 5;

    /**
     * Places a member. $parent and $leg are what the member asked for, if anything.
     *
     *  - No parent asked: the sponsor is the starting point.
     *  - A parent asked: it must be in the sponsor's own tree.
     *  - A leg asked: that slot if it is free, otherwise the next free slot
     *    down the same leg, level by level, left first.
     *  - No leg asked: the first free slot below the starting point, level by
     *    level, left first.
     *
     * The slot is claimed in one step that succeeds only if it is still free.
     * If another member took it first, the search runs again.
     */
    public function place(User $member, User $sponsor, ?User $parent = null, ?string $leg = null): TreePosition
    {
        if ($member->position()->exists()) {
            throw new PlacementException('This member already has a place in the tree.');
        }
        if ($leg !== null && ! in_array($leg, [TreePosition::LEFT, TreePosition::RIGHT], true)) {
            throw new PlacementException('The leg must be left or right.');
        }
        if (! TreePosition::whereKey($sponsor->id)->exists()) {
            throw new PlacementException("The sponsor @{$sponsor->username} has no place in the tree yet (their package is not paid), so this member cannot be placed under them.");
        }

        $start = $parent ?? $sponsor;
        if ($parent !== null && ! $this->isInTreeOf($parent->id, $sponsor->id)) {
            throw new PlacementException("@{$parent->username} is not in the sponsor's own tree, so they cannot be the parent.");
        }

        for ($attempt = 1; $attempt <= self::CLAIM_ATTEMPTS; $attempt++) {
            [$parentId, $slotLeg] = $this->findSlot($start->id, $leg);
            $depth = (int) TreePosition::whereKey($parentId)->value('depth') + 1;

            try {
                // A savepoint, so a lost race does not spoil the surrounding transaction.
                return DB::transaction(fn () => TreePosition::create([
                    'user_id' => $member->id, 'parent_id' => $parentId, 'leg' => $slotLeg, 'depth' => $depth,
                ]));
            } catch (UniqueConstraintViolationException) {
                // Someone else took that slot a moment ago: search again.
            }
        }

        throw new PlacementException('No free place could be claimed after several tries. Please try again.');
    }

    /**
     * The first free slot: [parent user id, leg].
     *
     * @return array{0: int, 1: string}
     */
    public function findSlot(int $startId, ?string $leg = null): array
    {
        $children = $this->children([$startId])[$startId] ?? [];

        if ($leg !== null) {
            if (! isset($children[$leg])) {
                return [$startId, $leg];
            }
            $level = [$children[$leg]]; // search the subtree under that leg
        } else {
            $level = [$startId];
        }

        // Level by level; within a level, in order; within a member, left first.
        while ($level !== []) {
            $kids = $this->children($level);
            $next = [];
            foreach ($level as $id) {
                foreach ([TreePosition::LEFT, TreePosition::RIGHT] as $side) {
                    if (! isset($kids[$id][$side])) {
                        return [$id, $side];
                    }
                }
                $next[] = $kids[$id][TreePosition::LEFT];
                $next[] = $kids[$id][TreePosition::RIGHT];
            }
            $level = $next;
        }

        throw new PlacementException('No free place was found in the tree.'); // cannot happen in a finite tree
    }

    /**
     * Every upline of a member, nearest first, with the leg the member sits
     * under at that upline. A loop (a member above themselves) stops the walk
     * and is logged for an admin.
     *
     * @return list<array{id: int, leg: string}>
     */
    public function uplines(User $member): array
    {
        $rows = DB::select('
            WITH RECURSIVE up (child_id, parent_id, leg, steps) AS (
                SELECT user_id, parent_id, leg, 0 FROM tree_positions WHERE user_id = ?
                UNION ALL
                SELECT t.user_id, t.parent_id, t.leg, up.steps + 1
                FROM tree_positions t JOIN up ON t.user_id = up.parent_id
                WHERE up.steps < 100000 AND up.parent_id <> ?
            )
            SELECT parent_id, leg FROM up WHERE parent_id IS NOT NULL ORDER BY steps
        ', [$member->id, $member->id]);

        $uplines = [];
        $seen = [$member->id => true];
        foreach ($rows as $row) {
            $id = (int) $row->parent_id;
            if (isset($seen[$id])) {
                NetworkAlert::create(['kind' => 'loop', 'user_id' => $member->id, 'details' => "A loop in the tree above @{$member->username}: member #{$id} appears twice. PV stopped climbing there."]);
                break;
            }
            $seen[$id] = true;
            $uplines[] = ['id' => $id, 'leg' => $row->leg];
        }

        return $uplines;
    }

    /** Whether $candidateId is $ancestorId or somewhere below them. */
    public function isInTreeOf(int $candidateId, int $ancestorId): bool
    {
        if ($candidateId === $ancestorId) {
            return TreePosition::whereKey($candidateId)->exists();
        }

        $candidate = User::find($candidateId);

        return $candidate !== null && in_array($ancestorId, array_column($this->uplines($candidate), 'id'), true);
    }

    /**
     * The children of each given member.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, int>> member id => [leg => child id]
     */
    public function children(array $ids): array
    {
        $out = [];
        TreePosition::whereIn('parent_id', $ids)->get(['user_id', 'parent_id', 'leg'])
            ->each(function (TreePosition $p) use (&$out) {
                $out[$p->parent_id][$p->leg] = $p->user_id;
            });

        return $out;
    }
}
