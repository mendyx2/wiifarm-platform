<?php

namespace Tests\Feature\Compensation;

use App\Models\Admin;
use App\Models\Compensation\LeadershipPayout;
use App\Models\Compensation\LeadershipRun;
use App\Models\Compensation\MatchRun;
use App\Models\Compensation\MemberMatch;
use App\Models\User;
use App\Services\Compensation\Leadership;
use App\Services\Ledger\Ledger;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The leadership bonus (specification section 5.7): 5% of the team's
 * matching for the month, to members with 480,000 repurchase PV.
 */
class LeadershipTest extends NetworkTestCase
{
    private Ledger $ledger;

    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(Ledger::class);
        $this->month = CarbonImmutable::now('Africa/Lagos')->format('Y-m');
    }

    private function repurchasePv(User $member, int $wholePv): void
    {
        $this->ledger->transfer($this->ledger->systemAccount('pv.issued'), $this->ledger->repurchasePvAccount($member), $wholePv * 100, 'test.pv', 'test:rpv:'.$member->id.':'.$wholePv);
    }

    private function leader(string $name = 'the_leader', int $wholePv = 480_000): User
    {
        $leader = $this->join($name);
        $this->repurchasePv($leader, $wholePv);

        return $leader;
    }

    /** Record that $member earned $kobo from matching on $day. */
    private function matched(User $member, int $kobo, ?string $day = null): void
    {
        $run = MatchRun::firstOrCreate(['day' => $day ?? $this->month.'-05'], ['status' => MatchRun::DONE]);
        MemberMatch::create([
            'match_run_id' => $run->id, 'user_id' => $member->id, 'package_id' => $member->package_id, 'left_pv' => 0, 'right_pv' => 0,
            'matches' => 1, 'capped' => false, 'amount' => $kobo, 'autoship' => 0, 'status' => MemberMatch::PAID,
        ]);
    }

    private function wallet(User $member): int
    {
        return $this->ledger->balances($member)['bonus.leadership'];
    }

    private function runMonth(): LeadershipRun
    {
        return app(Leadership::class)->run($this->month);
    }

    public function test_a_leader_earns_5_percent_of_their_whole_tree_teams_matching_once()
    {
        $leader = $this->leader();
        $child = $this->join('child_member', sponsor: $leader);
        $grandchild = $this->join('grandchild_member', sponsor: $child);
        $outsider = $this->join('outsider');

        $this->matched($child, 100_000);
        $this->matched($grandchild, 50_000);
        $this->matched($grandchild, 900_000, '2020-01-05');   // another month
        $this->matched($outsider, 900_000);                   // not in the team

        $run = $this->runMonth();
        $this->runMonth();                                     // again: pays nobody twice

        $this->assertSame(7_500, $this->wallet($leader));     // 5% of ₦1,500
        $line = LeadershipPayout::where('user_id', $leader->id)->sole();
        $this->assertSame([2, 150_000, 7_500, 'paid'], [$line->team_members, $line->team_matching, $line->amount, $line->status]);
        $this->assertSame(['binary tree, no limit', 1, 7_500], [$run->team_rule, $run->leaders, $run->amount]);
        $this->artisan('wiifarm:ledger-check')->assertSuccessful();
    }

    public function test_the_team_rule_is_a_setting_levels_or_the_sponsor_chain()
    {
        $leader = $this->leader();
        $child = $this->join('child_member', sponsor: $leader);
        $grandchild = $this->join('grandchild_member', sponsor: $child);
        $placedOnly = $this->join('placed_only', parent: $leader, leg: 'R');   // sponsored by the company, placed under the leader
        $this->matched($child, 100_000);
        $this->matched($grandchild, 50_000);
        $this->matched($placedOnly, 200_000);

        Settings::set('leadership.levels', 1);
        $this->runMonth();
        $this->assertSame(15_000, $this->wallet($leader));    // child and placed_only: 5% of ₦3,000

        LeadershipPayout::query()->delete();   // start the month again under the other rule
        LeadershipRun::query()->delete();
        Settings::set('leadership.team', 'sponsor');
        Settings::set('leadership.levels', 0);
        $line = $this->runMonth()->lines()->sole();
        $this->assertSame([2, 150_000], [$line->team_members, $line->team_matching]);   // child and grandchild, not placed_only
        $this->assertSame('sponsor chain, no limit', LeadershipRun::sole()->team_rule);
    }

    public function test_only_active_members_with_480000_repurchase_pv_qualify()
    {
        $nearly = $this->leader('nearly_there', 479_999);
        $banned = $this->leader('banned_leader');
        $banned->forceFill(['status' => User::STATUS_BANNED])->save();
        $quiet = $this->leader('quiet_leader');                  // qualifies, but the team earned nothing
        foreach ([$nearly, $banned, $quiet] as $who) {
            $this->matched($this->join('below_'.$who->id, sponsor: $who), 100_000);
        }
        MemberMatch::where('user_id', User::where('username', 'below_'.$quiet->id)->value('id'))->delete();

        $run = $this->runMonth();

        $this->assertSame([0, 0, 0], [$this->wallet($nearly), $this->wallet($banned), $this->wallet($quiet)]);
        $this->assertSame([$quiet->id], $run->lines()->pluck('user_id')->all());
        $this->assertSame(LeadershipPayout::NONE, $run->lines()->sole()->status);
    }

    public function test_a_payout_the_leadership_line_cannot_cover_is_held()
    {
        $leader = $this->leader();
        $this->matched($this->join('big_earner', sponsor: $leader), 1_000_000_000);   // ₦10M of team matching: ₦500,000 due

        $run = $this->runMonth();

        $this->assertSame(0, $this->wallet($leader));
        $this->assertSame(LeadershipPayout::HELD, $run->lines()->sole()->status);
        $this->assertSame(50_000_000, $run->stillHeld());
    }

    public function test_the_command_pays_last_month_and_refuses_one_not_ended()
    {
        $this->artisan('wiifarm:leadership')->assertSuccessful();
        $this->assertSame(Leadership::lastClosedMonth(), LeadershipRun::sole()->month);
        $this->artisan('wiifarm:leadership')->expectsOutputToContain('already done')->assertSuccessful();
        $this->artisan('wiifarm:leadership', ['--month' => $this->month])->assertFailed();
        $this->artisan('wiifarm:leadership', ['--month' => '2026-13'])->assertFailed();
    }

    public function test_admins_set_the_team_and_see_the_runs_and_members_see_their_progress()
    {
        $leader = $this->leader();
        $this->matched($this->join('child_member', sponsor: $leader), 100_000);
        $run = $this->runMonth();

        $super = Admin::factory()->super()->create();
        $this->actingAs($super, 'admin');
        $this->put('/admin/settings/leadership', ['team' => 'nobody', 'levels' => 5])->assertSessionHasErrors('team');
        $this->put('/admin/settings/leadership', ['team' => 'sponsor', 'levels' => 3])->assertSessionHasNoErrors();
        $this->assertSame(['sponsor', 3], [Settings::get('leadership.team'), Settings::get('leadership.levels')]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.edit', 'note' => 'Leadership bonus team']);
        $this->get('/admin/leadership')->assertInertia(fn ($page) => $page->where('rule', 'sponsor chain, 3 generations')->where('runs.data.0.month', $this->month));
        $this->get("/admin/leadership/{$run->id}")->assertInertia(fn ($page) => $page->where('lines.data.0.member.username', 'the_leader')->where('lines.data.0.amount', 5_000));

        $this->actingAs(Admin::factory()->withPermissions(['settings.view'])->create(), 'admin');
        $this->put('/admin/settings/leadership', ['team' => 'tree', 'levels' => 0])->assertForbidden();
        $this->get('/admin/leadership')->assertForbidden();

        $this->actingAs($leader)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('leadership.pv', 48_000_000)->where('leadership.needed', 48_000_000)->where('leadership.last.amount', 5_000));
    }
}
