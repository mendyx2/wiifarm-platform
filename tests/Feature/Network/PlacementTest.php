<?php

namespace Tests\Feature\Network;

use App\Models\TreePosition;
use App\Models\User;
use App\Services\Network\PlacementException;
use App\Services\Network\Registration;
use App\Services\Network\Tree;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Placement in the binary tree (specification section 3).
 */
class PlacementTest extends NetworkTestCase
{
    public function test_with_no_choice_members_fill_level_by_level_left_first()
    {
        $company = User::company();
        $a = $this->join('member_a');
        $b = $this->join('member_b');
        $c = $this->join('member_c');
        $d = $this->join('member_d');
        $e = $this->join('member_e');

        $this->assertSame(['wiifarm', 'L'], $this->placeOf($a));
        $this->assertSame(['wiifarm', 'R'], $this->placeOf($b));
        // next level: under A left, A right, then B left
        $this->assertSame(['member_a', 'L'], $this->placeOf($c));
        $this->assertSame(['member_a', 'R'], $this->placeOf($d));
        $this->assertSame(['member_b', 'L'], $this->placeOf($e));
        $this->assertSame(2, $e->position->depth);
        $this->assertSame(0, $company->position->depth);
    }

    public function test_a_sponsor_starts_the_search_from_their_own_place()
    {
        $a = $this->join('member_a');
        $this->join('member_b');
        $x = $this->join('member_x', sponsor: $a);
        $y = $this->join('member_y', sponsor: $a);
        $z = $this->join('member_z', sponsor: $a);

        $this->assertSame(['member_a', 'L'], $this->placeOf($x));
        $this->assertSame(['member_a', 'R'], $this->placeOf($y));
        $this->assertSame(['member_x', 'L'], $this->placeOf($z));
    }

    public function test_a_chosen_free_leg_is_used()
    {
        $a = $this->join('member_a');
        $x = $this->join('member_x', sponsor: $a, leg: 'R');

        $this->assertSame(['member_a', 'R'], $this->placeOf($x));
    }

    public function test_a_taken_leg_sends_the_member_down_that_same_leg()
    {
        $a = $this->join('member_a');
        $this->join('right_one', sponsor: $a, leg: 'R');   // A's right
        $this->join('left_one', sponsor: $a, leg: 'L');    // A's left
        $x = $this->join('member_x', sponsor: $a, leg: 'R');

        // A's right is taken, so the next free slot under right_one: its left.
        $this->assertSame(['right_one', 'L'], $this->placeOf($x));
    }

    public function test_a_chosen_parent_must_be_in_the_sponsors_tree()
    {
        $a = $this->join('member_a');
        $b = $this->join('member_b');
        $under_a = $this->join('under_a', sponsor: $a);

        // Under the sponsor's own downline: fine.
        $x = $this->join('member_x', sponsor: $a, parent: $under_a, leg: 'R');
        $this->assertSame(['under_a', 'R'], $this->placeOf($x));

        // B is not in A's tree: refused, and nothing happens.
        $member = User::factory()->create(['username' => 'member_y', 'sponsor_id' => $a->id]);
        $member->forceFill(['requested_parent_id' => $b->id])->save();

        try {
            app(Registration::class)->activate($member, $this->package('Starter'), 'pay:y');
            $this->fail('Placed outside the sponsor\'s tree.');
        } catch (PlacementException $e) {
            $this->assertStringContainsString('not in the sponsor\'s own tree', $e->getMessage());
        }

        $this->assertNull($member->fresh()->position);
        $this->assertFalse($member->fresh()->isActivated());
        $this->assertSame(0.0, $this->pv($b, 'L'));
    }

    public function test_a_sponsor_without_a_place_cannot_have_members_placed_under_them()
    {
        $pending = User::factory()->create(['username' => 'pending_sponsor', 'sponsor_id' => User::company()->id]);
        $member = User::factory()->create(['username' => 'member_x', 'sponsor_id' => $pending->id]);

        $this->expectException(PlacementException::class);
        app(Registration::class)->activate($member, $this->package('Starter'), 'pay:x');
    }

    public function test_a_slot_is_never_given_twice()
    {
        $a = $this->join('member_a');
        TreePosition::create(['user_id' => User::factory()->create()->id, 'parent_id' => $a->id, 'leg' => 'L', 'depth' => 2]);

        $this->expectException(UniqueConstraintViolationException::class);
        TreePosition::create(['user_id' => User::factory()->create()->id, 'parent_id' => $a->id, 'leg' => 'L', 'depth' => 2]);
    }

    public function test_a_member_who_loses_the_race_for_a_slot_is_placed_again()
    {
        $a = $this->join('member_a');
        $tree = new class extends Tree
        {
            public bool $raced = false;

            public function findSlot(int $startId, ?string $leg = null): array
            {
                $slot = parent::findSlot($startId, $leg);
                if (! $this->raced) {
                    // Someone else claims the slot between our search and our claim.
                    $this->raced = true;
                    TreePosition::create(['user_id' => User::factory()->create()->id, 'parent_id' => $slot[0], 'leg' => $slot[1], 'depth' => 2]);
                }

                return $slot;
            }
        };

        $member = User::factory()->create(['sponsor_id' => $a->id]);
        $position = $tree->place($member, $a);

        $this->assertSame([$a->id, 'R'], [$position->parent_id, $position->leg]);
    }

    public function test_a_place_cannot_be_changed()
    {
        $a = $this->join('member_a');

        $this->expectException(\LogicException::class);
        $a->position->update(['leg' => 'R']);
    }

    public function test_the_registration_preview_shows_where_a_member_would_go()
    {
        $a = $this->join('member_a');
        $member = User::factory()->create(['sponsor_id' => $a->id]);

        $preview = app(Registration::class)->preview($member);

        $this->assertSame([$a->id, 'left'], [$preview['parent']->id, $preview['leg']]);
    }
}
