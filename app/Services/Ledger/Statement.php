<?php

namespace App\Services\Ledger;

use App\Models\Ledger\Account;
use App\Models\Ledger\Entry;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A wallet's statement: its entries, newest first, each with the balance
 * just after it. Balances are worked out from the entries, never stored.
 */
class Statement
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function for(Account $account, int $perPage = 25): LengthAwarePaginator
    {
        $page = Entry::with('transaction:id,type,memo,created_at')
            ->where('account_id', $account->id)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $first = $page->getCollection()->first();
        $running = $first ? (int) Entry::where('account_id', $account->id)->where('id', '<=', $first->id)->sum('amount') : 0;

        return $page->through(function (Entry $entry) use (&$running) {
            $row = [
                'id' => $entry->id,
                'at' => $entry->created_at->toDateTimeString(),
                'type' => $entry->transaction->type,
                'memo' => $entry->transaction->memo,
                'transaction' => $entry->transaction_id,
                'amount' => $entry->amount,
                'balance' => $running,
            ];
            $running -= $entry->amount;

            return $row;
        });
    }
}
