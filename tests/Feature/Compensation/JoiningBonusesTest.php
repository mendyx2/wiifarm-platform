<?php

namespace Tests\Feature\Compensation;

use App\Models\Pools\UnclaimedBonus;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Network\Registration;
use App\Services\Network\Upgrade;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Direct, indirect, key-in and upgrade bonuses (specification sections 5.1 to 5.4).
 */
class JoiningBonusesTest extends NetworkTestCase
{
    private function wallet(User $member, string $wallet): int
    {
        return app(Ledger::class)->balances($member)[$wallet];
    }

    private function pool(string $pool, string $line): int
    {
        $l = app(Ledger::class);

        return $l->balance($l->poolAccount($pool, $line));
    }

    /** A sponsor chain of $n members under the company: [gen n, ..., gen 1]; the last is the sponsor. */
    private function chain(int $n): array
    {
        $members = [];
        $sponsor = null;
        for ($i = 1; $i <= $n; $i++) {
            $sponsor = $this->join("chain_{$i}", sponsor: $sponsor);
            $members[] = $sponsor;
        }

        return $members;
    }

    public function test_a_mega_sign_up_pays_the_plans_direct_and_indirect_bonuses()
    {
        $chain = $this->chain(12);                 // chain_12 is the sponsor
        $sponsor = $chain[11];
        $before = array_map(fn ($m) => $this->wallet($m, 'bonus.indirect'), $chain);

        $this->join('new_mega', sponsor: $sponsor, package: 'Mega');

        // 5.1: a Mega sign-up pays the sponsor ₦67,500.
        $this->assertSame(6_750_000, $this->wallet($sponsor, 'bonus.direct'));
        // 5.2: ₦8,100, then ₦5,400, then ₦2,700 to each of the next eight (chain_11 down to chain_2).
        $indirect = fn (int $i) => $this->wallet($chain[$i], 'bonus.indirect') - $before[$i];
        $this->assertSame(810_000, $indirect(10));  // generation 1: the sponsor's sponsor
        $this->assertSame(540_000, $indirect(9));
        foreach (range(8, 1) as $i) {
            $this->assertSame(270_000, $indirect($i), 'generation '.(11 - $i));
        }
        $this->assertSame(0, $indirect(0));          // the 11th generation above earns nothing
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_a_pre_starter_sign_up_and_where_unpaid_shares_go()
    {
        $sponsor = $this->join('the_sponsor');                       // sponsored by the company
        $contingencyBefore = $this->pool('A', 'contingency');
        $unclaimedBefore = $this->pool('A', 'unclaimed');
        $rowsBefore = UnclaimedBonus::max('id') ?? 0;

        $this->join('new_member', sponsor: $sponsor);                 // ₦9,000 of PV value

        $this->assertSame(225_000, $this->wallet($sponsor, 'bonus.direct'));     // 25%: ₦2,250
        // Indirect generation 1 is the company account and 2 to 10 do not exist; a self sign-up has no key-in.
        // What the indirect line holds (₦1,169.79 of the ₦1,170) and the ₦180 key-in go to Pool A's Unclaimed Bonus Reserve;
        // the contingency fund keeps only the 2.3% (₦172.50), the 0.01% of the net (65 kobo) and 4 kobo of rounding.
        $this->assertSame(116_979 + 18_000, $this->pool('A', 'unclaimed') - $unclaimedBefore);
        $this->assertSame(17_250 + 65 + 4, $this->pool('A', 'contingency') - $contingencyBefore);
        // Each inflow says where it came from.
        $this->assertSame(['company', 'no_upline'], UnclaimedBonus::where('id', '>', $rowsBefore)->where('bonus', 'indirect')->distinct()->orderBy('reason')->pluck('reason')->all());
        $this->assertSame('company', UnclaimedBonus::where('id', '>', $rowsBefore)->where(['bonus' => 'indirect', 'generation' => 1])->value('reason'));   // generation 1 is the company account
        $this->assertSame(9, UnclaimedBonus::where('id', '>', $rowsBefore)->where(['bonus' => 'indirect', 'reason' => 'no_upline'])->count());   // generations 2 to 10 do not exist
        $keyIn = UnclaimedBonus::where('id', '>', $rowsBefore)->where('bonus', 'keyin')->sole();
        $this->assertSame(['no_key_in', 18_000, 'new_member'], [$keyIn->reason, $keyIn->amount, $keyIn->source->username]);
    }

    public function test_the_member_who_keys_someone_in_earns_the_key_in_bonus()
    {
        $sponsor = $this->join('the_sponsor');
        $helper = $this->join('the_helper');
        $member = User::factory()->create(['username' => 'keyed_in', 'sponsor_id' => $sponsor->id]);

        app(Registration::class)->activate($member, $this->package('Mega'), 'visa:1', keyedInBy: $helper);
        $this->assertSame(540_000, $this->wallet($helper, 'bonus.keyin'));       // 2% of ₦270,000

        // A sponsor who keys in their own recruit earns both.
        $own = User::factory()->create(['username' => 'own_recruit', 'sponsor_id' => $sponsor->id]);
        app(Registration::class)->activate($own, $this->package('Pre Starter'), 'visa:2', keyedInBy: $sponsor);
        $this->assertSame(18_000, $this->wallet($sponsor, 'bonus.keyin'));
        $this->assertSame(6_750_000 + 225_000, $this->wallet($sponsor, 'bonus.direct'));
    }

    public function test_banned_members_earn_nothing_and_nobody_else_takes_their_share()
    {
        $top = $this->join('top_member');
        $banned = $this->join('banned_one', sponsor: $top);
        $sponsor = $this->join('the_sponsor', sponsor: $banned);
        $banned->forceFill(['status' => User::STATUS_BANNED])->save();
        $before = $this->wallet($top, 'bonus.indirect');

        $this->join('new_member', sponsor: $sponsor, package: 'Mega');

        $this->assertSame(0, $this->wallet($banned, 'bonus.indirect'));                 // generation 1, banned
        $this->assertSame(540_000, $this->wallet($top, 'bonus.indirect') - $before);    // still generation 2's 2%, not 3%
    }

    public function test_the_same_payment_never_pays_twice()
    {
        $sponsor = $this->join('the_sponsor');
        $member = User::factory()->create(['sponsor_id' => $sponsor->id]);

        app(Registration::class)->activate($member, $this->package('Starter'), 'pay:1');
        app(Registration::class)->activate($member->fresh(), $this->package('Starter'), 'pay:1');

        $this->assertSame(450_000, $this->wallet($sponsor, 'bonus.direct'));
    }

    public function test_an_upgrade_pays_its_bonuses_on_the_pv_difference()
    {
        $chain = $this->chain(3);
        $member = $this->join('upgrader', sponsor: $chain[2], package: 'Starter');

        app(Upgrade::class)->apply($member, $this->package('Builder'), 'pay:up:1');

        // 5.4: Starter to Builder is 30 PV, ₦18,000. The sponsor gets ₦4,500, generation 2 ₦360, then ₦180.
        $this->assertSame(450_000, $this->wallet($chain[2], 'bonus.upgrade'));
        $this->assertSame(36_000, $this->wallet($chain[1], 'bonus.upgrade'));
        $this->assertSame(18_000, $this->wallet($chain[0], 'bonus.upgrade'));
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }
}
