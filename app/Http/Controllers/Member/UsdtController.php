<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Usdt\TronGridUnavailable;
use App\Services\Usdt\UsdtDeposits;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** A member deposits USDT (TRC20) to Wiifarm's address (specification section 6). */
class UsdtController extends Controller
{
    public function start(Request $request, UsdtDeposits $usdt): RedirectResponse
    {
        $data = $request->validate(['usdt_amount' => ['required', 'string', 'max:20']]);
        try {
            $usdt->start($request->user(), Money::parseNaira($data['usdt_amount']));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['usdt_amount' => $e->getMessage()]);
        }

        return to_route('deposit')->with('status', 'Send the exact USDT amount shown within the hour.');
    }

    /** "I have paid": the member gives the transaction ID, or asks for a check now. */
    public function claim(Request $request, UsdtDeposits $usdt): RedirectResponse
    {
        $data = $request->validate(['txid' => ['nullable', 'string', 'max:80']]);
        try {
            if (blank($data['txid'] ?? null)) {
                // A check of the address now; at most one every 15 seconds for everyone.
                if (! RateLimiter::attempt('usdt-scan', 1, fn () => $usdt->scan(), 15)) {
                    return back()->with('status', 'Checked a moment ago. If you have just paid, wait a minute: the network needs about a minute to make a payment final.');
                }

                return back()->with('status', 'Checked. If your payment is final it has been added; if not, try again in a minute, or enter its transaction ID.');
            }

            return back()->with('status', $usdt->claim($request->user(), $data['txid']));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['txid' => $e->getMessage()]);
        } catch (TronGridUnavailable) {
            throw ValidationException::withMessages(['txid' => 'The Tron network could not be checked just now. Your payment is safe; try again in a minute.']);
        }
    }
}
