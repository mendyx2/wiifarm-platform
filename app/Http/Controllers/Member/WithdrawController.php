<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Controller;
use App\Models\Payments\Withdrawal;
use App\Models\Payments\WithdrawalMethod;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerException;
use App\Services\Payments\BonusTransfer;
use App\Services\Payments\Withdrawals;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** A member withdraws from their main balance, or a stockist from their stockist wallet (specification section 6). */
class WithdrawController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $member = $request->user();
        $store = Store::where('user_id', $member->id)->first();

        return Inertia::render('withdraw', [
            'wallets' => array_values(array_filter([
                ['key' => 'main', 'label' => 'Main balance', 'balance' => $ledger->balances($member)['main']],
                $store ? ['key' => 'store', 'label' => 'Stockist wallet ('.$store->name.')', 'balance' => $ledger->balance($ledger->storeWallet($store))] : null,
            ])),
            'methods' => WithdrawalController::methodOptions(),
            'blocked' => $this->refusal($member),
            'withdrawals' => Withdrawal::with('method:id,name')
                ->where(fn ($q) => $q->where(['holder_type' => Withdrawal::MEMBER, 'holder_id' => $member->id])
                    ->when($store, fn ($w) => $w->orWhere(fn ($s) => $s->where(['holder_type' => Withdrawal::STORE, 'holder_id' => $store->id]))))
                ->latest('id')->limit(20)->get()->map(fn (Withdrawal $w) => WithdrawalController::row($w)),
        ]);
    }

    public function store(Request $request, Withdrawals $withdrawals, Ledger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'wallet' => ['required', Rule::in(['main', 'store'])],
            'method_id' => ['required', 'integer', 'exists:withdrawal_methods,id'],
            'amount' => ['required', 'string', 'max:20'],
            'details' => ['array'],
            'form_id' => ['required', 'uuid'],
        ]);
        $member = $request->user();

        if ($reason = $this->refusal($member)) {
            return back()->withErrors(['amount' => $reason]);
        }

        if ($data['wallet'] === 'store') {
            $store = Store::where('user_id', $member->id)->firstOrFail();
            [$type, $id, $wallet] = [Withdrawal::STORE, $store->id, $ledger->storeWallet($store)];
        } else {
            [$type, $id, $wallet] = [Withdrawal::MEMBER, $member->id, $ledger->memberAccount($member, 'main')];
        }

        try {
            $withdrawals->request($type, $id, $wallet, WithdrawalMethod::findOrFail($data['method_id']), Money::parseNaira($data['amount']), $data['details'] ?? [], $data['form_id']);
        } catch (InsufficientFunds) {
            return back()->withErrors(['amount' => 'There is not enough in that wallet.']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Withdrawal requested. The money has left your wallet and an admin will send it shortly.');
    }

    /** Move money from a bonus wallet to the main balance. */
    public function bonusTransfer(Request $request, BonusTransfer $transfer): RedirectResponse
    {
        $data = $request->validate(['wallet' => ['required', 'string'], 'amount' => ['required', 'string', 'max:20'], 'form_id' => ['required', 'uuid']]);

        try {
            $transfer->move($request->user(), $data['wallet'], Money::parseNaira($data['amount']), $data['form_id']);
        } catch (InsufficientFunds) {
            return back()->withErrors(['transfer' => 'There is not that much in that bonus wallet.']);
        } catch (LedgerException|InvalidArgumentException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', 'Moved to your main balance.');
    }

    /** Why this member may not withdraw now, or null. */
    private function refusal(User $member): ?string
    {
        if ($member->withdrawals_blocked) {
            return 'Withdrawals are blocked on your account'.($member->withdrawals_block_reason ? ': '.$member->withdrawals_block_reason : '.').' Contact Wiifarm support.';
        }
        if (Settings::get('withdrawals.kyc_required') && $member->kyc_status !== 'verified') {
            return 'Verify your identity (KYC) before you withdraw. You can do it in Settings, under Identity.';
        }

        return null;
    }
}
