<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pools\AllocationSchedule;
use App\Models\Pools\HeldPayout;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PoolReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The pool ledgers, their contingency funds and held payouts (specification section 7). */
class PoolsController extends Controller
{
    public function __invoke(Request $request, PoolReport $report, Ledger $ledger): Response
    {
        $period = $request->validate(['period' => ['nullable', Rule::in(['today', 'month', 'quarter', 'all'])]])['period'] ?? 'month';
        $now = Carbon::now();
        [$from, $to] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'all' => [null, null],
        };

        $schedule = AllocationSchedule::current();

        return Inertia::render('admin/pools', [
            'period' => $period,
            'schedule' => ['version' => $schedule->version, 'source' => $schedule->source, 'effective_from' => $schedule->effective_from->toDateString()],
            ...$report->summary($from, $to),
            'held' => HeldPayout::with('toAccount.user:id,username')->where('status', HeldPayout::HELD)->orderBy('id')->limit(50)->get()
                ->map(fn (HeldPayout $h) => [
                    'id' => $h->id,
                    'pool' => $h->pool,
                    'line' => config("pools.pools.{$h->pool}.lines.{$h->line}") ?? $h->line,
                    'to' => ($h->toAccount->user ? '@'.$h->toAccount->user->username.' · ' : '').$ledger->label($h->toAccount),
                    'amount' => $h->amount,
                    'memo' => $h->memo,
                    'since' => $h->created_at->toDateTimeString(),
                ]),
        ]);
    }
}
