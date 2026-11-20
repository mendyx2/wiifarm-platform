<?php

namespace App\Http\Controllers\PublicShop;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Payments\OnlinePayment;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Models\Stockists\Store;
use App\Services\Payments\OnlinePayments;
use App\Services\Payments\PaymentException;
use App\Services\Payments\Paystack;
use App\Services\Shop\Orders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The public shop (specification section 8): anyone buys at the public
 * price, pays online, and collects from any stockist with a pickup code.
 * A buyer sent by an affiliate's link is remembered, so the affiliate earns
 * their share when the goods are collected.
 */
class PublicShopController extends Controller
{
    public const REF_COOKIE = 'wiifarm_ref';

    public function index(Request $request, Paystack $paystack): Response|RedirectResponse
    {
        // "?ref=WII-XXXXXX" works like the affiliate's /r/ link.
        if ($request->filled('ref')) {
            $affiliate = Affiliate::where('code', strtoupper((string) $request->query('ref')))->where('status', Affiliate::ACTIVE)->first();
            $response = redirect()->route('public.shop');

            return $affiliate ? $response->withCookie(self::refCookie($affiliate)) : $response;
        }

        $affiliate = $this->affiliate($request);

        return Inertia::render('public/shop', [
            'products' => Product::where('is_active', true)->orderBy('name')->get()->map(fn (Product $p) => [
                'id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'price' => $p->public_price, 'image' => $p->imageUrl(),
                'category_id' => $p->category_id,
            ]),
            'categories' => ProductCategory::forShop(),
            'referredBy' => $affiliate?->name(),
            'online' => $paystack->configured(),
        ]);
    }

    public function order(Request $request, Orders $orders, OnlinePayments $payments): HttpResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'email' => ['required', 'email', 'max:255'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:500'],
        ], ['phone.regex' => 'Enter a phone number in digits.']);

        try {
            $order = $orders->publicOrder($data['quantities'], ['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email']], $this->affiliate($request));
            [, $url] = $payments->startPublic($order, $data['email'], route('public.return'));
        } catch (InvalidArgumentException|PaymentException $e) {
            throw ValidationException::withMessages(['quantities' => $e->getMessage()]);
        }

        return Inertia::location($url);
    }

    /** Back from Paystack: our server asks Paystack what happened, then shows the order. */
    public function return(Request $request, OnlinePayments $payments): RedirectResponse
    {
        $payment = OnlinePayment::where('purpose', OnlinePayment::PUBLIC_ORDER)
            ->where('reference', (string) $request->query('reference', $request->query('trxref', '')))->first();
        if ($payment === null) {
            return redirect()->route('public.shop');
        }

        try {
            $payments->check($payment);
        } catch (PaymentException) {
            // Paystack's own message, or the regular check, will confirm it.
        }

        return redirect()->to(self::orderUrl($payment->order));
    }

    /** The buyer's own page for their order: a private link, with the pickup code once paid. */
    public function show(Order $order): Response
    {
        abort_unless($order->kind === Order::PUBLIC, 404);
        $order->load('items.product');
        $paid = in_array($order->status, [Order::PAID, Order::FULFILLED], true);

        return Inertia::render('public/order', [
            'order' => [
                'number' => $order->number, 'status' => $order->status, 'amount' => $order->amount, 'buyer' => $order->buyer_name,
                'items' => $order->items->map(fn ($i) => ['name' => $i->product->name, 'quantity' => $i->quantity, 'price' => $i->unit_price]),
                'code' => $order->status === Order::PAID ? $order->pickup_code : null,
                'paid_at' => $order->paid_at?->toDateTimeString(), 'fulfilled_at' => $order->fulfilled_at?->toDateTimeString(),
            ],
            'waiting' => ! $paid && OnlinePayment::where('order_id', $order->id)->where('status', OnlinePayment::PENDING)->exists(),
            'stores' => $paid ? Store::where('status', Store::ACTIVE)->orderBy('state')->orderBy('name')->get(['name', 'state', 'address', 'phone']) : [],
        ]);
    }

    /** The private link to a public order: signed, so nobody can guess another buyer's order. */
    public static function orderUrl(Order $order): string
    {
        return URL::signedRoute('public.order', ['order' => $order->id], absolute: false);
    }

    public static function refCookie(Affiliate $affiliate): Cookie
    {
        return cookie(self::REF_COOKIE, $affiliate->code, 60 * 24 * (int) config('wiifarm.affiliate_cookie_days'));
    }

    private function affiliate(Request $request): ?Affiliate
    {
        $code = $request->cookie(self::REF_COOKIE);

        return $code ? Affiliate::where('code', $code)->where('status', Affiliate::ACTIVE)->first() : null;
    }
}
