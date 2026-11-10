<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Services\Admin\WalletAdjustment;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use App\Services\Ledger\Statement;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** A member's wallet statements, and adding to or subtracting from a wallet. */
class MemberWalletController extends Controller
{
    public function show(User $member, string $wallet, Ledger $ledger, Statement $statement): Response
    {
        abort_unless(array_key_exists($wallet, config('ledger.member_wallets')), 404);

        return Inertia::render('admin/members/wallet', [
            'member' => ['id' => $member->id, 'username' => $member->username, 'name' => $member->name],
            'wallet' => $wallet,
            'label' => config('ledger.member_wallets')[$wallet],
            'balance' => $ledger->balances($member)[$wallet],
            'entries' => $statement->for($ledger->memberAccount($member, $wallet)),
        ]);
    }

    public function adjust(Request $request, User $member, WalletAdjustment $adjustment): RedirectResponse
    {
        $data = $request->validate([
            'wallet' => ['required', Rule::in(array_keys(config('ledger.member_wallets')))],
            'direction' => ['required', Rule::in(['add', 'subtract'])],
            'amount' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'max:250'],
            'form_id' => ['required', 'uuid'],
        ], ['reason.required' => 'Give a reason. It is shown on the member\'s statement.']);

        try {
            $kobo = Money::parseNaira($data['amount']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        /** @var Admin $admin */
        $admin = $request->user('admin');

        try {
            $adjustment->apply($admin, $member, $data['wallet'], $data['direction'] === 'add', $kobo, $data['reason'], $data['form_id']);
        } catch (LedgerException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Wallet updated.');
    }
}
