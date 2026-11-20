<?php

namespace Tests\Feature\Compensation;

use App\Models\Admin;
use App\Models\Compensation\Award;
use App\Models\Pools\UnclaimedBonus;
use App\Models\User;
use App\Services\Compensation\Awards;
use App\Services\Ledger\Ledger;
use App\Services\Network\PvClimb;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Recognition awards, the Upline Celebrated Bonus and repurchase awards
 * (specification 5.8, 5.9 and 5.11; plan PDF and Revised Baseline Rules).
 */
class AwardsTest extends NetworkTestCase
{
    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
    }

    /** A member on $package with $left / $right whole lifetime PV; [member, left child, right child]. @return array{0: User, 1: User, 2: User} */
    private function withLegs(string $name, int $left, int $right, string $package = 'Mega', ?User $sponsor = null): array
    {
        $top = $this->join($name, sponsor: $sponsor, package: $package);
        $l = $this->join("{$name}_l", sponsor: $top, leg: 'L');
        $r = $this->join("{$name}_r", sponsor: $top, leg: 'R');
        $climb = app(PvClimb::class);
        $climb->credit($l, max(1, $left - 15) * 100, PvClimb::SOURCE_PURCHASE, "t:{$name}:l");
        $climb->credit($r, max(1, $right - 15) * 100, PvClimb::SOURCE_PURCHASE, "t:{$name}:r");

        return [$top->fresh(), $l, $r];
    }

    private function fund(string $pool, string $line, int $kobo): void
    {
        $this->ledger->transfer($this->ledger->systemAccount('adjustments'), $this->ledger->poolAccount($pool, $line), $kobo, 'test.fund', 'fund:'.Str::uuid());
    }

    private function detect(): int
    {
        return app(Awards::class)->detect();
    }

    /** @return list<string> */
    private function ranksOf(User $member): array
    {
        return Award::where(['user_id' => $member->id, 'kind' => Award::RANK])->orderBy('id')->pluck('code')->all();
    }

    private function super(): Admin
    {
        return Admin::factory()->super()->create();
    }

    public function test_a_rank_needs_the_lesser_leg_pv_and_the_package_and_is_recorded_once()
    {
        [$builder] = $this->withLegs('builder_one', 3_500, 3_200, 'Builder');
        [$small] = $this->withLegs('small_one', 2_000, 2_000, 'Pre Starter');

        $this->detect();
        $this->detect();   // again: nothing new

        $this->assertSame(['emerald'], $this->ranksOf($builder));    // Silver needs the Booster package
        $this->assertSame([], $this->ranksOf($small));               // Emerald needs Builder
        $award = Award::where('user_id', $builder->id)->sole();
        $this->assertSame([Award::PENDING, 8_000_000, 3_200, null], [$award->status, $award->amount, $award->measure, $award->gift]);
    }

    public function test_from_gold_up_the_rank_below_must_be_produced_on_both_legs()
    {
        [$mega, $left, $right] = $this->withLegs('mega_one', 13_000, 13_000);

        $this->detect();
        $this->assertSame(['emerald', 'silver', 'jade'], $this->ranksOf($mega));   // no Jade below yet, so no Gold

        Award::create(['user_id' => $left->id, 'kind' => Award::RANK, 'code' => 'jade', 'name' => 'Jade', 'amount' => 1, 'measure' => 1, 'status' => Award::PAID]);
        $this->detect();
        $this->assertSame(['emerald', 'silver', 'jade'], $this->ranksOf($mega));   // only on the left

        Award::create(['user_id' => $right->id, 'kind' => Award::RANK, 'code' => 'diamond', 'name' => 'Diamond', 'amount' => 1, 'measure' => 1, 'status' => Award::PAID]);
        $this->detect();
        $this->assertSame(['emerald', 'silver', 'jade', 'gold'], $this->ranksOf($mega));   // a Diamond counts as "a Jade or higher"
    }

    public function test_approving_pays_the_cash_and_the_upline_celebrated_bonus()
    {
        $sponsor = $this->join('the_sponsor', package: 'Starter');
        [$member] = $this->withLegs('emerald_one', 1_500, 1_500, 'Builder', $sponsor);
        $this->detect();
        $award = Award::sole();
        $this->fund('B', 'recognition', 8_000_000);
        $this->fund('B', 'ucb', 1_000_000);
        $contingency = $this->ledger->balance($this->ledger->poolAccount('B', 'contingency'));

        $this->actingAs($this->super(), 'admin')->post("/admin/awards/{$award->id}/approve")->assertSessionHas('status', 'Emerald approved and paid.');

        $this->assertSame(8_000_000, $this->ledger->balances($member)['bonus.awards']);
        $this->assertSame(400_000, $this->ledger->balances($sponsor)['bonus.acb']);      // 5% of ₦80,000
        // Generations 2 and 3 are the company account and nobody: their 2% and 1% go to Pool B's Unclaimed Bonus Reserve.
        $this->assertSame(0, $this->ledger->balance($this->ledger->poolAccount('B', 'contingency')) - $contingency);
        $this->assertSame(240_000, $this->ledger->balance($this->ledger->poolAccount('B', 'unclaimed')));
        $this->assertSame([2 => 'company', 3 => 'no_upline'], UnclaimedBonus::where('bonus', 'ucb')->orderBy('generation')->pluck('reason', 'generation')->all());
        $this->assertSame(Award::PAID, $award->fresh()->status);
        $this->post("/admin/awards/{$award->id}/approve")->assertSessionHasErrors('award');   // decided once
        $this->assertDatabaseHas('audit_logs', ['action' => 'awards.approve']);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_member_may_choose_the_gift_and_its_value_is_set_aside()
    {
        [$mega, $left, $right] = $this->withLegs('mega_one', 13_000, 13_000);
        foreach ([$left, $right] as $leg) {
            Award::create(['user_id' => $leg->id, 'kind' => Award::RANK, 'code' => 'jade', 'name' => 'Jade', 'amount' => 1, 'measure' => 1, 'status' => Award::PAID]);
        }
        $this->detect();
        $gold = Award::where(['user_id' => $mega->id, 'code' => 'gold'])->sole();
        $emerald = Award::where(['user_id' => $mega->id, 'code' => 'emerald'])->sole();

        $this->actingAs($mega)->post("/awards/{$emerald->id}/choice", ['choice' => 'gift'])->assertSessionHasErrors(['choice' => 'This award is cash only.']);
        $this->post("/awards/{$gold->id}/choice", ['choice' => 'gift'])->assertSessionHasNoErrors();
        $this->assertSame('gift', $gold->fresh()->choice);

        $this->fund('B', 'recognition', 55_000_000);
        $this->fund('B', 'ucb', 10_000_000);
        $this->actingAs($this->super(), 'admin')->post("/admin/awards/{$gold->id}/approve");

        $this->assertSame(Award::GIFT, $gold->fresh()->status);
        $this->assertSame(55_000_000, $this->ledger->balance($this->ledger->cooperativeAccount('award_gifts')));
        $this->assertSame(0, $this->ledger->balances($mega)['bonus.awards']);
        $this->post("/admin/awards/{$gold->id}/delivered");
        $this->assertSame(Award::DELIVERED, $gold->fresh()->status);

        $this->actingAs($mega)->post("/awards/{$gold->id}/choice", ['choice' => 'cash'])->assertSessionHasErrors('choice');   // decided
    }

    public function test_an_award_the_pool_cannot_cover_is_held_then_paid_and_a_refused_one_says_why()
    {
        [$member] = $this->withLegs('emerald_one', 1_500, 1_500, 'Builder');
        [$other] = $this->withLegs('emerald_two', 1_600, 1_600, 'Builder');
        $this->detect();
        [$first, $second] = Award::orderBy('id')->get()->all();
        $this->actingAs($this->super(), 'admin');

        $this->post("/admin/awards/{$first->id}/approve");
        $this->assertSame(Award::HELD, $first->fresh()->currentStatus());           // Pool B holds only a few naira from sign-ups

        $this->fund('B', 'recognition', 8_000_000);
        $this->artisan('wiifarm:pay-held')->assertSuccessful();
        $this->assertSame(Award::PAID, $first->fresh()->currentStatus());
        $this->assertSame(8_000_000, $this->ledger->balances($member)['bonus.awards']);

        $this->post("/admin/awards/{$second->id}/reject", ['reason' => 'PV from a cancelled order'])->assertSessionHasNoErrors();
        $this->actingAs($other)->get('/awards')->assertInertia(fn ($page) => $page
            ->where('awards.0.status', 'rejected')->where('awards.0.reason', 'PV from a cancelled order')->where('ranks.0.earned', false));
    }

    public function test_repurchase_awards_pay_the_difference_from_the_last_tier()
    {
        $member = $this->join('the_leader', package: 'Mega');
        $chain = [];
        $sponsor = $member;
        for ($g = 1; $g <= 11; $g++) {
            $sponsor = $this->join("gen_{$g}", sponsor: $sponsor);
            $chain[$g] = $sponsor;
        }
        $buy = fn (User $who, int $pv, string $key) => $this->ledger->transfer($this->ledger->systemAccount('pv.issued'), $this->ledger->repurchasePvAccount($who), $pv * 100, 'test.pv', $key);

        $buy($chain[1], 3_000, 'b1');
        $buy($chain[10], 3_000, 'b2');
        $buy($chain[11], 50_000, 'b3');   // the 11th generation does not count
        $this->detect();
        $first = Award::where(['user_id' => $member->id, 'kind' => Award::REPURCHASE])->sole();
        $this->assertSame(['5000', 25_000_000, 6_000], [$first->code, $first->amount, $first->measure]);

        $buy($chain[5], 15_000, 'b4');    // 21,000 PV: past 10,000 and 20,000 at once
        $this->detect();
        $second = Award::where(['user_id' => $member->id, 'kind' => Award::REPURCHASE])->orderByDesc('id')->first();
        $this->assertSame(['20000', 75_000_000], [$second->code, $second->amount]);   // ₦1,000,000 − ₦250,000

        $this->fund('C', 'repurchase_award', 100_000_000);
        $this->actingAs($this->super(), 'admin');
        foreach ([$first, $second] as $award) {
            $this->post("/admin/awards/{$award->id}/approve");
        }
        $this->assertSame(100_000_000, $this->ledger->balances($member)['bonus.repurchase_award']);
    }

    public function test_admin_permissions_and_the_members_page()
    {
        [$member] = $this->withLegs('emerald_one', 1_700, 1_600, 'Builder');
        $this->detect();
        $award = Award::sole();

        $this->actingAs(Admin::factory()->withPermissions(['awards.view'])->create(), 'admin');
        $this->get('/admin/awards')->assertInertia(fn ($page) => $page->where('awards.data.0.member.username', 'emerald_one'));
        $this->post("/admin/awards/{$award->id}/approve")->assertForbidden();

        $this->actingAs($member)->get('/awards')->assertInertia(fn ($page) => $page
            ->where('lesserLeg', 1_600)->where('ranks.0.earned', true)->where('ranks.1.name', 'Silver')->where('awards.0.status', 'pending'));
    }
}
