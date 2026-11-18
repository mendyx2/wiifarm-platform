<?php

namespace App\Http\Controllers;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\OnlinePayments;
use App\Services\Payments\Paystack;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Paystack's own message that a payment succeeded (its "webhook"). It is
 * signed with our secret key; an unsigned or wrongly signed message is
 * refused. Paystack sends again until it gets a 200, and a repeat changes
 * nothing.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, Paystack $paystack, OnlinePayments $payments): Response
    {
        if (! $paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response('Invalid signature', 401);
        }

        if ($request->input('event') === 'charge.success') {
            $facts = Paystack::facts((array) $request->input('data', []));
            $payment = OnlinePayment::where('reference', $facts['reference'])->first();
            if ($payment !== null) {
                $payments->confirm($payment, $facts);
            }
        }

        return response('OK', 200);
    }
}
