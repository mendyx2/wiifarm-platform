<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payments\ManualDeposit;
use App\Models\Payments\OnlinePayment;
use App\Models\Payments\UsdtDeposit;
use App\Models\Payments\Withdrawal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reports for admins (specification section 11): the financial report by
 * period, every ledger transaction, and the login history. The pool ledgers
 * by period are on the Pools page.
 */
class ReportController extends Controller
{
    public function financial(Request $request): Response
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'month', 'last_month', 'quarter', 'year', 'custom'])],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $period = $data['period'] ?? 'month';
        $zone = config('wiifarm.business_timezone');
        $now = CarbonImmutable::now($zone);
        [$from, $to] = match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'year' => [$now->startOfYear(), $now->endOfYear()],
            'custom' => [CarbonImmutable::parse($data['from'] ?? $now->startOfMonth()->toDateString(), $zone)->startOfDay(), CarbonImmutable::parse($data['to'] ?? $now->toDateString(), $zone)->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
        [$f, $t] = [$from->utc(), $to->utc()];

        $paystack = OnlinePayment::where('status', OnlinePayment::PAID)->whereBetween('paid_at', [$f, $t])
            ->groupBy('purpose')->selectRaw('purpose, COUNT(*) as n, SUM(amount) as amount')->get()->keyBy('purpose');
        $purposes = [OnlinePayment::PACKAGE => 'Paystack: packages', OnlinePayment::UPGRADE => 'Paystack: upgrades', OnlinePayment::DEPOSIT => 'Paystack: deposits', OnlinePayment::PUBLIC_ORDER => 'Paystack: public shop'];
        $in = [];
        foreach ($purposes as $purpose => $label) {
            $in[] = ['label' => $label, 'count' => (int) ($paystack[$purpose]->n ?? 0), 'amount' => (int) ($paystack[$purpose]->amount ?? 0)];
        }
        $bank = ManualDeposit::where('status', ManualDeposit::APPROVED)->whereBetween('decided_at', [$f, $t]);
        $in[] = ['label' => 'Bank transfers', 'count' => (clone $bank)->count(), 'amount' => (int) $bank->sum('amount')];
        $usdt = UsdtDeposit::where('status', UsdtDeposit::PAID)->whereBetween('updated_at', [$f, $t]);
        $in[] = ['label' => 'USDT', 'count' => (clone $usdt)->count(), 'amount' => (int) $usdt->sum('credited')];

        $paid = Withdrawal::where('status', Withdrawal::PAID)->whereBetween('decided_at', [$f, $t]);
        $entries = fn () => DB::table('ledger_entries')->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_entries.amount', '>', 0)->whereBetween('ledger_entries.created_at', [$f, $t]);
        $labels = config('ledger.member_wallets');

        return Inertia::render('admin/reports/financial', [
            'period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'moneyIn' => $in,
            'withdrawals' => ['count' => (clone $paid)->count(), 'sent' => (int) (clone $paid)->sum('payable'), 'charges' => (int) $paid->sum('charge')],
            'bonuses' => $entries()->whereNotNull('ledger_accounts.user_id')->where('ledger_accounts.kind', 'like', 'bonus.%')
                ->groupBy('ledger_accounts.kind')->selectRaw('ledger_accounts.kind as kind, SUM(ledger_entries.amount) as amount')->orderByDesc('amount')->get()
                ->map(fn ($r) => ['label' => $labels[$r->kind] ?? $r->kind, 'amount' => (int) $r->amount]),
            'cooperative' => $entries()->where('ledger_accounts.holder', 'cooperative')
                ->groupBy('ledger_accounts.kind')->selectRaw('ledger_accounts.kind as kind, SUM(ledger_entries.amount) as amount')->get()
                ->map(fn ($r) => ['label' => config('ledger.cooperative_accounts')[$r->kind] ?? $r->kind, 'amount' => (int) $r->amount]),
            'members' => User::whereBetween('activated_at', [$f, $t])->count(),
        ]);
    }

    public function transactions(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:60'], 'member' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $zone = config('wiifarm.business_timezone');
        $memberId = ($data['member'] ?? null) ? User::where('username', ltrim($data['member'], '@'))->value('id') ?? 0 : null;

        $page = DB::table('ledger_transactions')
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', CarbonImmutable::parse($d, $zone)->startOfDay()->utc()))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', CarbonImmutable::parse($d, $zone)->endOfDay()->utc()))
            ->when($memberId !== null, fn ($q) => $q->whereIn('id', DB::table('ledger_entries')->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
                ->where('ledger_accounts.user_id', $memberId)->select('ledger_entries.transaction_id')))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        $lines = DB::table('ledger_entries')->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->leftJoin('users', 'users.id', '=', 'ledger_accounts.user_id')
            ->whereIn('ledger_entries.transaction_id', collect($page->items())->pluck('id'))
            ->get(['ledger_entries.transaction_id', 'ledger_entries.amount', 'ledger_accounts.holder', 'ledger_accounts.kind', 'ledger_accounts.currency', 'users.username'])
            ->groupBy('transaction_id');

        return Inertia::render('admin/reports/transactions', [
            'filters' => ['type' => $data['type'] ?? '', 'member' => $data['member'] ?? '', 'from' => $data['from'] ?? '', 'to' => $data['to'] ?? ''],
            'types' => DB::table('ledger_transactions')->distinct()->orderBy('type')->pluck('type'),
            'transactions' => $page->through(fn ($tx) => [
                'id' => $tx->id, 'type' => $tx->type, 'memo' => $tx->memo, 'by_admin' => $tx->admin_id !== null,
                'at' => CarbonImmutable::parse($tx->created_at, 'UTC')->setTimezone($zone)->toDateTimeString(),
                'lines' => ($lines[$tx->id] ?? collect())->map(fn ($l) => ['account' => self::account($l), 'amount' => (int) $l->amount, 'currency' => $l->currency])->values(),
            ]),
        ]);
    }

    public function logins(Request $request): Response
    {
        $data = $request->validate(['who' => ['nullable', 'string', 'max:120'], 'guard' => ['nullable', Rule::in(['web', 'admin', 'affiliate'])]]);
        $zone = config('wiifarm.business_timezone');

        return Inertia::render('admin/reports/logins', [
            'filters' => ['who' => $data['who'] ?? '', 'guard' => $data['guard'] ?? ''],
            'logins' => DB::table('login_logs')
                ->when($data['guard'] ?? null, fn ($q, $g) => $q->where('guard', $g))
                ->when($data['who'] ?? null, fn ($q, $w) => $q->where('name', ltrim($w, '@')))
                ->orderByDesc('id')->paginate(50)->withQueryString()
                ->through(fn ($l) => ['id' => $l->id, 'guard' => $l->guard, 'name' => $l->name, 'ip' => $l->ip, 'agent' => $l->user_agent,
                    'at' => CarbonImmutable::parse($l->created_at, 'UTC')->setTimezone($zone)->toDateTimeString()]),
        ]);
    }

    /** An account in words: "@ada: Main balance", "Pool A: indirect", "Cooperative: Withdrawal charges". */
    private static function account(object $l): string
    {
        $wallets = config('ledger.member_wallets');

        return match (true) {
            $l->username !== null => '@'.$l->username.': '.($wallets[$l->kind] ?? $l->kind),
            str_starts_with($l->holder, 'pool:') => 'Pool '.substr($l->holder, 5).': '.str_replace('_', ' ', $l->kind),
            $l->holder === 'cooperative' => 'Cooperative: '.(config('ledger.cooperative_accounts')[$l->kind] ?? $l->kind),
            default => ucfirst(str_replace([':', '_'], [' ', ' '], $l->holder)).': '.str_replace('_', ' ', $l->kind),
        };
    }
}
