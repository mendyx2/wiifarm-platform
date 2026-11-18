<?php

namespace App\Services\Pools;

use App\Models\Pools\HeldPayout;
use App\Models\Pools\Manufacturer;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Each pool ledger, line by line: what came in and went out in a period, and
 * what it holds now; its contingency fund; and what is held waiting for
 * money. The manufacturing partners' accounts beside them.
 */
class PoolReport
{
    /** @return array<string, mixed> */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where(fn ($q) => $q->where('ledger_accounts.holder', 'like', 'pool:%')->orWhere('ledger_accounts.holder', 'like', 'manufacturer:%'))
            ->groupBy('ledger_accounts.holder', 'ledger_accounts.kind')
            ->selectRaw('ledger_accounts.holder as holder, ledger_accounts.kind as kind, SUM(ledger_entries.amount) as balance')
            ->selectRaw('SUM(CASE WHEN ledger_entries.amount > 0 AND ledger_entries.created_at >= ? AND ledger_entries.created_at <= ? THEN ledger_entries.amount ELSE 0 END) as period_in', [$from ?? '0000-01-01', $to ?? '9999-12-31'])
            ->selectRaw('SUM(CASE WHEN ledger_entries.amount < 0 AND ledger_entries.created_at >= ? AND ledger_entries.created_at <= ? THEN -ledger_entries.amount ELSE 0 END) as period_out', [$from ?? '0000-01-01', $to ?? '9999-12-31'])
            ->get()
            ->keyBy(fn ($r) => $r->holder.'|'.$r->kind);

        $held = HeldPayout::where('status', HeldPayout::HELD)
            ->groupBy('pool', 'line')
            ->selectRaw('pool, line, COUNT(*) as count, SUM(amount) as amount')
            ->get()->keyBy(fn ($h) => $h->pool.'|'.$h->line);

        $figures = function (string $holder, string $kind) use ($rows) {
            $r = $rows[$holder.'|'.$kind] ?? null;

            return ['balance' => (int) ($r->balance ?? 0), 'in' => (int) ($r->period_in ?? 0), 'out' => (int) ($r->period_out ?? 0)];
        };

        $pools = [];
        foreach (config('pools.pools') as $code => $pool) {
            $lines = [];
            foreach ($pool['lines'] + [Ledger::CONTINGENCY => config('pools.contingency')] as $line => $label) {
                $h = $held[$code.'|'.$line] ?? null;
                $lines[] = ['key' => $line, 'label' => $label, 'contingency' => $line === Ledger::CONTINGENCY]
                    + $figures('pool:'.$code, $line)
                    + ['held_count' => (int) ($h->count ?? 0), 'held_amount' => (int) ($h->amount ?? 0)];
            }
            $pools[] = [
                'code' => $code,
                'name' => $pool['name'],
                'lines' => $lines,
                'balance' => array_sum(array_column($lines, 'balance')),
                'in' => array_sum(array_column($lines, 'in')),
                'out' => array_sum(array_column($lines, 'out')),
            ];
        }

        $manufacturers = Manufacturer::orderBy('name')->get()->map(fn (Manufacturer $m) => ['id' => $m->id, 'name' => $m->name] + $figures('manufacturer:'.$m->id, 'share'))->all();

        return ['pools' => $pools, 'manufacturers' => $manufacturers];
    }
}
