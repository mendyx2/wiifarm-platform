<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Shop\Orders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Members buy products (repurchase) and see their orders with pickup codes (specification section 8). */
class ShopController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $balances = $ledger->balances($request->user());

        return Inertia::render('shop', [
            'products' => Product::where('is_active', true)->orderBy('name')->get()
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'price' => $p->member_price, 'pv' => $p->pv]),
            'wallets' => ['main' => $balances['main'], 'product' => $balances['product']],
            'canBuy' => $request->user()->isActivated(),
        ]);
    }

    public function store(Request $request, Orders $orders): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:10000'],
            'wallet' => ['required', Rule::in(Orders::WALLETS)],
            'form_id' => ['required', 'uuid'],
        ]);

        try {
            $order = $orders->repurchase($request->user(), $data['quantities'], $data['wallet'], $data['form_id']);
        } catch (InsufficientFunds) {
            return back()->withErrors(['wallet' => 'There is not enough in that wallet for this order.']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantities' => $e->getMessage()]);
        }

        return to_route('orders.index')->with('status', "Order {$order->number} is paid. Show its pickup code at any stockist to collect it.");
    }

    public function orders(Request $request): Response
    {
        return Inertia::render('orders', [
            'orders' => Order::with('items.product:id,name')->where('user_id', $request->user()->id)->latest('id')->paginate(20)
                ->through(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'kind' => $o->kind,
                    'status' => $o->status,
                    'amount' => $o->amount,
                    'pv' => $o->pv,
                    'units' => $o->units,
                    // Only the buyer is shown their code, and only until the goods are collected.
                    'pickup_code' => $o->status === Order::PAID ? $o->pickup_code : null,
                    'paid_at' => $o->paid_at?->toDateTimeString(),
                    'fulfilled_at' => $o->fulfilled_at?->toDateTimeString(),
                    'items' => $o->items->map(fn ($i) => ['name' => $i->product->name, 'quantity' => $i->quantity]),
                ]),
        ]);
    }
}
