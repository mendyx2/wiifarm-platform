<?php

namespace App\Services\Network;

use App\Models\Ledger\Entry;
use App\Models\Ledger\Transaction;
use App\Models\TreePosition;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * What the genealogy screens show: a few levels of the tree below a member,
 * with each person's package and PV, and a member's PV log.
 */
class Genealogy
{
    public const LEVELS = 4;

    public function __construct(private readonly Tree $tree) {}

    /**
     * The tree below $root, $levels deep (the root is level 1). A free place
     * is null.
     *
     * @return array<string, mixed>
     */
    public function subtree(User $root, int $levels = self::LEVELS): array
    {
        $ids = [$root->id];
        $level = [$root->id];
        $children = [];
        for ($i = 1; $i < $levels && $level !== []; $i++) {
            $kids = $this->tree->children($level);
            $children += $kids;
            $level = array_values(array_merge(...array_map('array_values', $kids ?: [[]])));
            $ids = array_merge($ids, $level);
        }

        $users = User::with('package:id,name')->whereIn('id', $ids)->get()->keyBy('id');
        $pv = $this->pvFor($ids);
        $hasChildren = TreePosition::whereIn('parent_id', $ids)->distinct()->pluck('parent_id')->flip();

        $build = function (int $id, int $depth) use (&$build, $users, $pv, $children, $levels, $hasChildren) {
            $u = $users[$id];
            $node = [
                'id' => $u->id,
                'username' => $u->username,
                'name' => $u->name,
                'package' => $u->package?->name,
                'banned' => $u->isBanned(),
                'pv' => $pv[$id] ?? ['L' => ['lifetime' => 0, 'pairing' => 0], 'R' => ['lifetime' => 0, 'pairing' => 0]],
                'more_below' => $depth === $levels && isset($hasChildren[$id]),
            ];
            if ($depth < $levels) {
                foreach (['L', 'R'] as $leg) {
                    $node['children'][$leg] = isset($children[$id][$leg]) ? $build($children[$id][$leg], $depth + 1) : null;
                }
            }

            return $node;
        };

        return $build($root->id, 1);
    }

    /**
     * A member's PV log: one row per PV credit, newest first, with the
     * lifetime and pairing PV it added on each leg.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function pvLog(User $member, int $perPage = 25): LengthAwarePaginator
    {
        $accountIds = DB::table('ledger_accounts')->where('holder', 'member:'.$member->id)->where('currency', 'PV')->pluck('kind', 'id');

        $page = Transaction::query()
            ->whereIn('id', Entry::whereIn('account_id', $accountIds->keys())->select('transaction_id'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $entries = Entry::whereIn('transaction_id', $page->getCollection()->pluck('id'))->whereIn('account_id', $accountIds->keys())->get()->groupBy('transaction_id');

        return $page->through(function (Transaction $t) use ($entries, $accountIds) {
            $row = ['id' => $t->id, 'at' => $t->created_at->toDateTimeString(), 'source' => $t->type, 'memo' => $t->memo, 'leg' => null, 'lifetime' => 0, 'pairing' => 0];
            foreach ($entries[$t->id] ?? [] as $e) {
                [, $leg, $type] = explode('.', $accountIds[$e->account_id]);
                $row['leg'] = $leg;
                $row[$type] += $e->amount;
            }

            return $row;
        });
    }

    /**
     * Lifetime and pairing PV on each leg, for many members at once.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, array<string, int>>>
     */
    public function pvFor(array $ids): array
    {
        $rows = DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->whereIn('ledger_accounts.user_id', $ids)
            ->where('ledger_accounts.currency', 'PV')
            ->groupBy('ledger_accounts.user_id', 'ledger_accounts.kind')
            ->selectRaw('ledger_accounts.user_id as user_id, ledger_accounts.kind as kind, SUM(ledger_entries.amount) as total')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            [, $leg, $type] = explode('.', $r->kind);
            $out[$r->user_id] ??= ['L' => ['lifetime' => 0, 'pairing' => 0], 'R' => ['lifetime' => 0, 'pairing' => 0]];
            $out[$r->user_id][$leg][$type] = (int) $r->total;
        }

        return $out;
    }
}
