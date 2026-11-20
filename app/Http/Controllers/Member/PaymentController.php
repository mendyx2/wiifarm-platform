<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payments\DepositMethod;
use App\Models\Payments\ManualDeposit;
use App\Models\Payments\OnlinePayment;
use App\Models\Payments\UsdtDeposit;
use App\Services\Network\PlacementException;
use App\Services\Payments\EPins;
use App\Services\Payments\ManualDeposits;
use App\Services\Payments\OnlinePayments;
use App\Services\Payments\PaymentException;
use App\Services\Payments\Paystack;
use App\Services\Usdt\UsdtDeposits;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * A member pays online through Paystack: their package (to become a member),
 * an upgrade, or a deposit to the main balance (specification sections 2, 4
 * and 6). What was paid for happens only when Paystack confirms it.
 */
class PaymentController extends Controller
{
    /** Choose and pay a package, or upgrade the one already held. */
    public function package(Request $request, Paystack $paystack): Response
    {
        $member = $request->user()->load('package');
        $current = $member->package;

        return Inertia::render('package', [
            'current' => $current && $member->isActivated() ? ['id' => $current->id, 'name' => $current->name, 'pv' => $current->pv, 'upgradable' => $current->upgradable] : null,
            'packages' => Package::where('is_active', true)->orderBy('sort')->get()->map(fn (Package $p) => [
                'id' => $p->id, 'name' => $p->name, 'price' => $p->price, 'pv' => $p->pv, 'packs' => $p->product_packs,
                'per_match' => $p->perMatch(), 'cap' => $p->daily_cap_matches,
                'to_pay' => $current && $member->isActivated() ? $p->price - $current->price : $p->price,
            ]),
            'online' => $paystack->configured(),
            'payments' => $this->recent($member->id, [OnlinePayment::PACKAGE, OnlinePayment::UPGRADE]),
        ]);
    }

    public function payPackage(Request $request, OnlinePayments $payments): HttpResponse
    {
        $data = $request->validate(['package_id' => ['required', 'integer', 'exists:packages,id']]);
        $member = $request->user();
        $purpose = $member->isActivated() ? OnlinePayment::UPGRADE : OnlinePayment::PACKAGE;

        return $this->startOrFail(fn () => $payments->start($member, $purpose, Package::findOrFail($data['package_id']), null, route('payments.return')), 'package_id');
    }

    /** A person who has signed up pays their package with an e-pin. */
    public function redeemPin(Request $request, EPins $epins): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);
        $package = Package::findOrFail($data['package_id']);

        try {
            $epins->redeem($request->user(), $data['code'], $package);
        } catch (PaymentException|PlacementException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        return to_route('package')->with('status', "E-pin accepted. Welcome: you are now a member on {$package->name}.");
    }

    public function deposit(Request $request, Paystack $paystack): Response
    {
        return Inertia::render('deposit', [
            'online' => $paystack->configured(),
            'min' => config('payments.deposit_min'),
            'max' => config('payments.deposit_max'),
            'payments' => $this->recent($request->user()->id, [OnlinePayment::DEPOSIT]),
            // USDT (TRC20) to Wiifarm's address (section 6).
            'usdt' => UsdtDeposits::ready() ? [
                'open' => ($d = UsdtDeposit::where('user_id', $request->user()->id)->where('status', UsdtDeposit::WAITING)->where('expires_at', '>', now())->latest('id')->first())
                    ? ['id' => $d->id, 'amount' => $d->amount, 'usdt' => UsdtDeposit::usdt($d->units), 'address' => $d->address, 'expires_at' => $d->expires_at->toIso8601String()] : null,
                'rate' => UsdtDeposits::rateKobo(),
                'network' => config('usdt.network'),
            ] : null,
            'usdtDeposits' => UsdtDeposit::where('user_id', $request->user()->id)->latest('id')->limit(10)->get()
                ->map(fn (UsdtDeposit $d) => ['id' => $d->id, 'amount' => $d->amount, 'usdt' => UsdtDeposit::usdt($d->units), 'status' => $d->status, 'note' => $d->note, 'at' => $d->created_at->toDateTimeString()]),
            // Bank transfer and other manual methods (section 6).
            'methods' => DepositMethod::where('is_active', true)->orderBy('name')->get()
                ->map(fn (DepositMethod $m) => ['id' => $m->id, 'name' => $m->name, 'instructions' => $m->instructions, 'min' => $m->min_amount, 'max' => $m->max_amount]),
            'manual' => ManualDeposit::with('method:id,name')->where('user_id', $request->user()->id)->latest('id')->limit(10)->get()
                ->map(fn (ManualDeposit $d) => [
                    'id' => $d->id, 'method' => $d->method->name, 'amount' => $d->amount, 'reference' => $d->reference,
                    'status' => $d->status, 'reason' => $d->reject_reason, 'at' => $d->created_at->toDateTimeString(),
                ]),
        ]);
    }

    /** A deposit by bank transfer (or another manual method), with proof, for an admin to check (section 6). */
    public function depositManual(Request $request, ManualDeposits $deposits): RedirectResponse
    {
        $data = $request->validate([
            'method_id' => ['required', 'integer', 'exists:deposit_methods,id'],
            'amount' => ['required', 'string', 'max:20'],
            'reference' => ['required', 'string', 'max:120'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'form_id' => ['required', 'uuid'],
        ], ['proof.mimes' => 'Upload a photo (JPG, PNG) or a PDF of the receipt.', 'proof.max' => 'The file can be up to 5 MB.']);

        try {
            $amount = Money::parseNaira($data['amount']);
            $deposits->submit($request->user(), DepositMethod::findOrFail($data['method_id']), $amount, $data['reference'], $request->file('proof'), $data['form_id']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return to_route('deposit')->with('status', 'Thank you. Your deposit will be added to your main balance once an admin has checked the payment.');
    }

    public function payDeposit(Request $request, OnlinePayments $payments): HttpResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:20']]);
        try {
            $amount = Money::parseNaira($data['amount']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return $this->startOrFail(fn () => $payments->start($request->user(), OnlinePayment::DEPOSIT, null, $amount, route('payments.return')), 'amount');
    }

    /** The member is back from Paystack. Our server asks Paystack what happened; the browser's word is not taken. */
    public function return(Request $request, OnlinePayments $payments): RedirectResponse
    {
        $reference = (string) $request->query('reference', $request->query('trxref', ''));
        $payment = OnlinePayment::where('reference', $reference)->where('user_id', $request->user()->id)->first();
        if ($payment === null) {
            return redirect()->route('dashboard')->with('status', 'That payment was not found.');
        }

        try {
            $payment = $payments->check($payment);
        } catch (PaymentException) {
            // Paystack's own message will confirm it, or the regular check will.
        }

        $back = $payment->purpose === OnlinePayment::DEPOSIT ? 'deposit' : 'package';

        return redirect()->route($back)->with('status', match ($payment->status) {
            OnlinePayment::PAID => $payment->note ?? match ($payment->purpose) {
                OnlinePayment::PACKAGE => "Payment received. Welcome: you are now a member on {$payment->package?->name}.",
                OnlinePayment::UPGRADE => "Payment received. You are now on {$payment->package?->name}.",
                default => 'Payment received: ₦'.number_format($payment->amount / 100, 2).' is in your main balance.',
            },
            OnlinePayment::FAILED => 'The payment was not completed. Nothing was taken by us; you can try again.',
            OnlinePayment::MISMATCH => 'Paystack reported a different amount. Nothing was credited yet; our team will look into it.',
            default => 'We are waiting for Paystack to confirm your payment. This page will show it as soon as it does.',
        });
    }

    /** @param callable(): array{0: OnlinePayment, 1: string} $start */
    private function startOrFail(callable $start, string $field): HttpResponse
    {
        try {
            [, $url] = $start();
        } catch (PaymentException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return Inertia::location($url);
    }

    /**
     * @param  list<string>  $purposes
     * @return list<array<string, mixed>>
     */
    private function recent(int $userId, array $purposes): array
    {
        return OnlinePayment::with('package:id,name')->where('user_id', $userId)->whereIn('purpose', $purposes)->latest('id')->limit(10)->get()
            ->map(fn (OnlinePayment $p) => [
                'id' => $p->id, 'what' => $p->describe(), 'amount' => $p->amount, 'reference' => $p->reference,
                'status' => $p->status, 'note' => $p->note, 'at' => $p->created_at->toDateTimeString(),
            ])->all();
    }
}
