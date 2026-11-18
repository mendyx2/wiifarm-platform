<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Payments\OnlinePayment;
use App\Services\Admin\Audit;
use App\Services\Payments\OnlinePayments;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Online payments through Paystack, and asking Paystack about one (specification section 6). */
class OnlinePaymentController extends Controller
{
    private const FILTERS = ['all', OnlinePayment::PENDING, OnlinePayment::PAID, OnlinePayment::FAILED, OnlinePayment::MISMATCH];

    public function index(Request $request): Response
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(self::FILTERS)], 'q' => ['nullable', 'string', 'max:60']]);
        $status = $data['status'] ?? 'all';
        $q = (string) ($data['q'] ?? '');

        return Inertia::render('admin/payments', [
            'status' => $status,
            'q' => $q,
            'payments' => OnlinePayment::with(['user:id,username,first_name,last_name', 'package:id,name', 'order:id,number,buyer_name,buyer_phone'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('reference', 'like', "%{$q}%")->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$q}%"))->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$q}%")->orWhere('buyer_name', 'like', "%{$q}%"))))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (OnlinePayment $p) => [
                    'id' => $p->id, 'reference' => $p->reference, 'what' => $p->describe(), 'amount' => $p->amount,
                    'status' => $p->status, 'gateway_status' => $p->gateway_status, 'channel' => $p->channel, 'note' => $p->note,
                    'member' => $p->user ? ['id' => $p->user->id, 'username' => $p->user->username, 'name' => trim($p->user->first_name.' '.$p->user->last_name)] : null,
                    'buyer' => $p->order ? trim($p->order->buyer_name.' · '.$p->order->buyer_phone, ' ·') : null,
                    'at' => $p->created_at->toDateTimeString(), 'paid_at' => $p->paid_at?->toDateTimeString(),
                    // Waiting, or failed after Paystack saw it: a payment that never reached Paystack has nothing to ask about.
                    'can_check' => $p->status === OnlinePayment::PENDING || ($p->status === OnlinePayment::FAILED && $p->gateway_status !== null),
                ]),
        ]);
    }

    /** For a payment still waiting or failed: ask Paystack now. Paystack's answer decides; the admin cannot mark it paid. */
    public function check(Request $request, OnlinePayment $payment, OnlinePayments $payments, Audit $audit): RedirectResponse
    {
        if (in_array($payment->status, [OnlinePayment::PAID, OnlinePayment::MISMATCH], true)) {
            return back()->with('status', "{$payment->reference} is already settled.");
        }

        try {
            $payment = $payments->check($payment);
        } catch (PaymentException $e) {
            return back()->with('status', $e->getMessage());
        }

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $audit->record($admin, 'payments.check', $payment, null, ['status' => $payment->status, 'gateway_status' => $payment->gateway_status]);

        return back()->with('status', "Paystack says {$payment->reference} is \"{$payment->gateway_status}\". It is now {$payment->status}.");
    }
}
