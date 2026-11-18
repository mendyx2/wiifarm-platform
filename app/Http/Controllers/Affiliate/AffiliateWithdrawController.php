<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Payments\Withdrawal;
use App\Models\Payments\WithdrawalMethod;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Payments\Withdrawals;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** An affiliate withdraws from their affiliate wallet. */
class AffiliateWithdrawController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        return Inertia::render('affiliate/withdraw', [
            'wallets' => [['key' => 'affiliate', 'label' => 'Affiliate wallet', 'balance' => $ledger->balance($ledger->affiliateWallet($affiliate))]],
            'methods' => WithdrawalController::methodOptions(),
            'withdrawals' => Withdrawal::with('method:id,name')->where(['holder_type' => Withdrawal::AFFILIATE, 'holder_id' => $affiliate->id])
                ->latest('id')->limit(20)->get()->map(fn (Withdrawal $w) => WithdrawalController::row($w)),
        ]);
    }

    public function store(Request $request, Withdrawals $withdrawals, Ledger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'method_id' => ['required', 'integer', 'exists:withdrawal_methods,id'],
            'amount' => ['required', 'string', 'max:20'],
            'details' => ['array'],
            'form_id' => ['required', 'uuid'],
        ]);
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        try {
            $withdrawals->request(Withdrawal::AFFILIATE, $affiliate->id, $ledger->affiliateWallet($affiliate), WithdrawalMethod::findOrFail($data['method_id']), Money::parseNaira($data['amount']), $data['details'] ?? [], $data['form_id']);
        } catch (InsufficientFunds) {
            return back()->withErrors(['amount' => 'There is not enough in your affiliate wallet.']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Withdrawal requested. An admin will send it shortly.');
    }
}
