<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Compensation\LeadershipPayout;
use App\Models\Compensation\MemberMatch;
use App\Models\Ledger\Entry;
use App\Models\Shop\Order;
use App\Models\User;
use App\Services\Compensation\MonthlyActivity;
use App\Services\Ledger\Ledger;
use App\Services\Network\Genealogy;
use App\Services\Network\Tree;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The member's dashboard: their money, PV, team and what needs doing, at a glance. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Ledger $ledger, Genealogy $genealogy, Tree $tree): Response
    {
        $member = $request->user()->load('package');
        if (! $member->isActivated()) {
            return Inertia::render('dashboard', ['member' => null]);
        }

        $balances = $ledger->balances($member);
        $bonusKinds = array_values(array_filter(array_keys($balances), fn ($k) => str_starts_with($k, 'bonus.')));
        $bonusAccounts = $ledger->memberAccountIds($member, $bonusKinds);
        $package = $member->package;
        $lastMatch = MemberMatch::with('run:id,day')->where('user_id', $member->id)->latest('id')->first();

        return Inertia::render('dashboard', [
            'member' => [
                'first_name' => $member->first_name,
                'package' => $package ? ['name' => $package->name, 'pv' => $package->pv, 'cap' => $package->daily_cap_matches, 'per_match' => $package->perMatch()] : null,
                'joined' => $member->activated_at->toDateString(),
                'kyc' => $member->kyc_status,
                'referral_link' => route('register', ['ref' => $member->username]),
            ],
            'money' => [
                'main' => $balances['main'],
                'visa' => $balances['visa'],
                'product' => $balances['product'],
                'autoship' => $balances['autoship'],
                'bonuses' => array_sum(array_intersect_key($balances, array_flip($bonusKinds))),
                // Everything ever paid into the bonus wallets, whether moved on since or not.
                'earned' => (int) Entry::whereIn('account_id', $bonusAccounts)->where('amount', '>', 0)->sum('amount'),
            ],
            'pv' => $genealogy->pvFor([$member->id])[$member->id] ?? null,
            'team' => [
                'direct' => User::where('sponsor_id', $member->id)->whereNotNull('activated_at')->count(),
                'waiting' => User::where('sponsor_id', $member->id)->whereNull('activated_at')->count(),
                'size' => $tree->teamSize($member),
            ],
            'lastMatch' => $lastMatch ? [
                'day' => $lastMatch->run->day, 'matches' => $lastMatch->matches, 'amount' => $lastMatch->amount,
                'capped' => $lastMatch->capped, 'status' => $lastMatch->currentStatus(),
            ] : null,
            // This month's monthly purchase: it lifts the autoship hold and keeps the unilevel bonus (when those rules are on).
            'autoship' => Settings::get('matching.autoship_hold') || Settings::get('unilevel.activity_required') ? [
                'bought' => app(MonthlyActivity::class)->creditFor($member, MonthlyActivity::monthOf(CarbonImmutable::now())),
                'needed' => MonthlyActivity::requiredPv(),
                'hold' => (bool) Settings::get('matching.autoship_hold'),
                'unilevel' => (bool) Settings::get('unilevel.activity_required'),
            ] : null,
            'leadership' => [
                'pv' => min(($pvLegs = $ledger->pvBalances($member))['L']['lifetime'], $pvLegs['R']['lifetime']),   // the lesser leg
                'needed' => (int) config('compensation.leadership.qualifying_pv'),
                'last' => ($l = LeadershipPayout::with('run:id,month')->where('user_id', $member->id)->latest('id')->first())
                    ? ['month' => $l->run->month, 'amount' => $l->amount, 'status' => $l->currentStatus()] : null,
            ],
            'toCollect' => Order::where('user_id', $member->id)->where('status', Order::PAID)->count(),
            'recent' => Entry::with('transaction:id,memo,created_at', 'account:id,kind')
                ->whereIn('account_id', $bonusAccounts)->where('amount', '>', 0)->latest('id')->limit(6)->get()
                ->map(fn (Entry $e) => [
                    'id' => $e->id, 'at' => $e->created_at->toDateString(), 'amount' => $e->amount,
                    'wallet' => config('ledger.member_wallets')[$e->account->kind] ?? $e->account->kind, 'memo' => $e->transaction->memo,
                ]),
        ]);
    }
}
