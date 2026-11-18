<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** All orders. Admins never see a pickup code, only its last four characters. */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in([Order::PENDING, Order::PAID, Order::FULFILLED, Order::CANCELLED])],
            'kind' => ['nullable', Rule::in([Order::REGISTRATION, Order::REPURCHASE, Order::PUBLIC])],
        ]);

        $orders = Order::with('user:id,username')
            ->when($filters['q'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', '%'.$s.'%')
                ->orWhere('buyer_name', 'like', '%'.$s.'%')
                ->orWhereHas('user', fn (Builder $u) => $u->where('username', 'like', '%'.$s.'%'))))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['kind'] ?? null, fn (Builder $q, string $k) => $q->where('kind', $k))
            ->latest('id')->paginate(25)->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'kind' => $o->kind,
                'status' => $o->status,
                'buyer' => $o->user ? '@'.$o->user->username : ($o->buyer_name ?? '—'),
                'amount' => $o->amount,
                'units' => $o->units,
                'created_at' => $o->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/orders/index', ['orders' => $orders, 'filters' => (object) array_filter($filters)]);
    }

    public function show(Order $order): Response
    {
        $order->load(['items.product:id,name,sku', 'user:id,username,first_name,last_name']);

        return Inertia::render('admin/orders/show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'kind' => $order->kind,
                'status' => $order->status,
                'amount' => $order->amount,
                'pv' => $order->pv,
                'units' => $order->units,
                'paid_with' => $order->paid_with,
                'paid_at' => $order->paid_at?->toDateTimeString(),
                'fulfilled_at' => $order->fulfilled_at?->toDateTimeString(),
                'created_at' => $order->created_at->toDateTimeString(),
                'code_hint' => $order->pickup_code_hint,
                'member' => $order->user ? ['id' => $order->user->id, 'username' => $order->user->username, 'name' => $order->user->name] : null,
                'buyer' => $order->user ? null : ['name' => $order->buyer_name, 'phone' => $order->buyer_phone, 'email' => $order->buyer_email],
                'items' => $order->items->map(fn ($i) => ['name' => $i->product->name, 'sku' => $i->product->sku, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price, 'unit_pv' => $i->unit_pv]),
            ],
        ]);
    }
}
