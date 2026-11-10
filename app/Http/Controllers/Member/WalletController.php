<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\Statement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The member's wallets: every balance, and the statement of one wallet. */
class WalletController extends Controller
{
    public function __invoke(Request $request, Ledger $ledger, Statement $statement): Response
    {
        $wallet = $request->validate(['wallet' => ['nullable', Rule::in(array_keys(config('ledger.member_wallets')))]])['wallet'] ?? 'main';
        $member = $request->user();

        return Inertia::render('wallet', [
            'balances' => $ledger->balances($member),
            'labels' => config('ledger.member_wallets'),
            'wallet' => $wallet,
            'entries' => $statement->for($ledger->memberAccount($member, $wallet)),
        ]);
    }
}
