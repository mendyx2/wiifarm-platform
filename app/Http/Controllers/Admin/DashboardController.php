<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Compensation\Award;
use App\Models\KycSubmission;
use App\Models\Payments\OnlinePayment;
use App\Models\Payments\Withdrawal;
use App\Models\Pools\HeldPayout;
use App\Models\Shop\Order;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Pools\PoolReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin home page (specification section 11): what is waiting for an
 * admin, the money in and out today and this month, members, and the pools.
 * Each part shows only to an admin allowed to see it.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, PoolReport $pools): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $can = fn (string $permission) => $admin->hasPermission($permission);

        $zone = config('wiifarm.business_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay()->utc();
        $month = CarbonImmutable::now($zone)->startOfMonth()->utc();
        $periods = ['today' => $today, 'month' => $month];

        // Things waiting for an admin, with where to deal with them.
        $todo = array_values(array_filter([
            $can('kyc.view') ? ['label' => 'Identity checks to review', 'count' => KycSubmission::where('status', KycSubmission::PENDING)->count(), 'amount' => null, 'href' => route('admin.kyc.index')] : null,
            $can('withdrawals.view') ? ['label' => 'Withdrawals to pay or refuse', 'count' => Withdrawal::where('status', Withdrawal::PENDING)->count(), 'amount' => (int) Withdrawal::where('status', Withdrawal::PENDING)->sum('payable'), 'href' => route('admin.withdrawals.index')] : null,
            $can('awards.view') ? ['label' => 'Awards to approve', 'count' => Award::where('status', Award::PENDING)->count(), 'amount' => null, 'href' => route('admin.awards.index')] : null,
            $can('awards.view') ? ['label' => 'Gifts to deliver', 'count' => Award::where('status', Award::GIFT)->count(), 'amount' => null, 'href' => route('admin.awards.index')] : null,
            $can('stockists.view') ? ['label' => 'Stores waiting to be activated', 'count' => Store::where('status', Store::PENDING)->count(), 'amount' => null, 'href' => route('admin.stockists.index')] : null,
            $can('payments.view') ? ['label' => 'Online payments that did not match', 'count' => OnlinePayment::where('status', OnlinePayment::MISMATCH)->count(), 'amount' => null, 'href' => route('admin.payments.index')] : null,
            $can('pools.view') ? ['label' => 'Payouts held until a pool refills', 'count' => HeldPayout::where('status', HeldPayout::HELD)->count(), 'amount' => (int) HeldPayout::where('status', HeldPayout::HELD)->sum('amount'), 'href' => route('admin.pools')] : null,
            $can('messages.view') ? ['label' => 'Emails that failed (last 7 days)', 'count' => DB::table('message_log')->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(), 'amount' => null, 'href' => route('admin.messages.index')] : null,
        ]));

        // Money in and out, today and this month (Lagos time).
        $money = [];
        if ($can('payments.view')) {
            $money[] = ['label' => 'Paid online (Paystack)', 'tone' => 'success'] + array_map(fn ($from) => (int) OnlinePayment::where('status', OnlinePayment::PAID)->where('paid_at', '>=', $from)->sum('amount'), $periods);
        }
        if ($can('withdrawals.view')) {
            $money[] = ['label' => 'Withdrawals paid', 'tone' => 'warning'] + array_map(fn ($from) => (int) Withdrawal::where('status', Withdrawal::PAID)->where('decided_at', '>=', $from)->sum('payable'), $periods);
        }
        if ($can('pools.view')) {
            $money[] = ['label' => 'Bonuses paid to members', 'tone' => 'info'] + array_map(fn ($from) => (int) DB::table('ledger_entries')
                ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
                ->whereNotNull('ledger_accounts.user_id')->where('ledger_accounts.kind', 'like', 'bonus.%')
                ->where('ledger_entries.amount', '>', 0)->where('ledger_entries.created_at', '>=', $from)->sum('ledger_entries.amount'), $periods);
        }

        return Inertia::render('admin/dashboard', [
            'todo' => $todo,
            'money' => $money,
            'members' => $can('members.view') ? [
                'total' => User::count(),
                'active' => User::whereNotNull('activated_at')->count(),
                'joinedToday' => User::where('activated_at', '>=', $today)->count(),
                'joinedMonth' => User::where('activated_at', '>=', $month)->count(),
                'banned' => User::where('status', User::STATUS_BANNED)->count(),
            ] : null,
            'orders' => $can('orders.view') ? [
                'toCollect' => Order::where('status', Order::PAID)->count(),
                'paidToday' => Order::whereIn('status', [Order::PAID, Order::FULFILLED])->where('paid_at', '>=', $today)->count(),
            ] : null,
            'pools' => $can('pools.view') ? collect($pools->summary()['pools'])->map(fn ($p) => ['code' => $p['code'], 'name' => $p['name'], 'balance' => $p['balance']])->values() : null,
        ]);
    }
}
