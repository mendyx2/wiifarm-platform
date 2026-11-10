<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Checks that the books balance (specification section 1). Runs every night;
 * any finding is logged as an error for the team to look at.
 *
 *     php artisan wiifarm:ledger-check
 */
class LedgerCheck extends Command
{
    protected $signature = 'wiifarm:ledger-check';

    protected $description = 'Check that every ledger transaction adds up to zero and no member wallet is below zero';

    public function handle(): int
    {
        $problems = [];

        // 1. Every transaction adds up to zero, per currency.
        $unbalanced = DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->groupBy('ledger_entries.transaction_id', 'ledger_accounts.currency')
            ->havingRaw('SUM(ledger_entries.amount) <> 0')
            ->pluck('ledger_entries.transaction_id');
        foreach ($unbalanced as $id) {
            $problems[] = "Transaction #{$id} does not add up to zero.";
        }

        // 2. Every transaction has at least two entries.
        $thin = DB::table('ledger_transactions')
            ->leftJoin('ledger_entries', 'ledger_entries.transaction_id', '=', 'ledger_transactions.id')
            ->groupBy('ledger_transactions.id')
            ->havingRaw('COUNT(ledger_entries.id) < 2')
            ->pluck('ledger_transactions.id');
        foreach ($thin as $id) {
            $problems[] = "Transaction #{$id} has fewer than two entries.";
        }

        // 3. No member wallet is below zero.
        $negative = DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_accounts.holder', '!=', 'system')
            ->groupBy('ledger_accounts.id', 'ledger_accounts.holder', 'ledger_accounts.kind')
            ->havingRaw('SUM(ledger_entries.amount) < 0')
            ->get(['ledger_accounts.holder', 'ledger_accounts.kind']);
        foreach ($negative as $row) {
            $problems[] = "Wallet {$row->kind} of {$row->holder} is below zero.";
        }

        // 4. The whole ledger adds up to zero, per currency.
        $totals = DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->groupBy('ledger_accounts.currency')
            ->selectRaw('ledger_accounts.currency as currency, SUM(ledger_entries.amount) as total')
            ->pluck('total', 'currency');
        foreach ($totals as $currency => $sum) {
            if ((int) $sum !== 0) {
                $problems[] = "All {$currency} entries together add up to {$sum}, not zero.";
            }
        }

        if ($problems === []) {
            $this->info('The ledger balances: '.DB::table('ledger_transactions')->count().' transactions checked.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }
        Log::error('Ledger check found problems', ['problems' => $problems]);

        return self::FAILURE;
    }
}
