<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payments\EPin;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Payments\EPins;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** A member buys e-pins from their VISA wallet and sees their pins (specification section 6). */
class EPinController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $member = $request->user();

        return Inertia::render('epins', [
            'visa' => $ledger->balances($member)['visa'],
            'canBuy' => $member->isActivated() && ! $member->isBanned(),
            'max' => EPins::MAX_PER_FORM,
            'packages' => Package::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'price', 'pv']),
            'pins' => EPin::with(['package:id,name', 'usedBy:id,username'])->where('created_by_user_id', $member->id)->latest('id')->paginate(25)
                ->through(fn (EPin $p) => [
                    'id' => $p->id, 'code' => $p->status === EPin::ACTIVE ? $p->code() : null, 'amount' => $p->amount,
                    'package' => $p->package?->name, 'status' => $p->status, 'used_by' => $p->usedBy?->username,
                    'used_at' => $p->used_at?->toDateTimeString(), 'created_at' => $p->created_at->toDateTimeString(),
                ]),
        ]);
    }

    public function buy(Request $request, EPins $epins): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.EPins::MAX_PER_FORM],
            'form_id' => ['required', 'uuid'],
        ]);

        try {
            $pins = $epins->buy($request->user(), Package::findOrFail($data['package_id']), (int) $data['quantity'], $data['form_id']);
        } catch (InsufficientFunds) {
            throw ValidationException::withMessages(['quantity' => 'Your VISA wallet does not have enough for this.']);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        return back()->with('status', $pins->count().' e-pin(s) ready. Give each code to the person it is for: it pays their package once.');
    }
}
