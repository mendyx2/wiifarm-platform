<?php

namespace App\Services\Stockists;

use App\Models\Pools\Manufacturer;
use App\Models\Shop\Order;
use App\Models\Stockists\Store;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Pools\PoolPayouts;
use App\Services\Shop\Orders;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handing an order over by its pickup code (specification sections 8 and 9).
 * The stockist enters the code, sees the order, and confirms. In one step:
 * the whole order is marked collected, the store's stock goes down, and the
 * store's rebate (₦120 a unit for a Mega store, ₦90 for a Sub store) is paid
 * to its stockist wallet from the ledger that funded the order.
 */
class Handover
{
    /** Which pool pays the rebate for each kind of order. */
    private const POOL = [Order::REGISTRATION => 'A', Order::REPURCHASE => 'C', Order::UPGRADE => 'E'];

    public function __construct(private readonly Orders $orders, private readonly Ledger $ledger, private readonly PoolPayouts $payouts) {}

    /** What the stockist sees after typing the code. @return array<string, mixed> */
    public function preview(Store $store, string $code): array
    {
        $order = $this->find($store, $code);

        return [
            'number' => $order->number,
            'kind' => $order->kind,
            'units' => $order->units,
            // Enough for the stockist to greet the buyer, nothing more.
            'buyer' => $order->user ? $order->user->first_name.' (@'.$order->user->username.')' : ($order->buyer_name ?? 'Public buyer'),
            'items' => $order->items->map(fn ($i) => ['name' => $i->product->name, 'quantity' => $i->quantity])->values()->all(),
            'rebate' => $order->units * $this->rate($store),
        ];
    }

    public function fulfil(Store $store, string $code): Order
    {
        return DB::transaction(function () use ($store, $code) {
            $order = Order::lockForUpdate()->findOrFail($this->find($store, $code)->id);
            if ($order->status !== Order::PAID) {
                throw new InvalidArgumentException('This order has just been collected.');
            }
            $order->load('items.product');

            // 1. The store's stock goes down (refused if it is short).
            foreach ($order->items as $item) {
                try {
                    $this->ledger->transfer($this->ledger->stockAccount($store, $item->product), $this->ledger->stockSystemAccount('delivered', $item->product), $item->quantity, 'stock.handover', "handover:{$order->id}:i{$item->id}", "Order {$order->number}", $order);
                } catch (InsufficientFunds) {
                    throw new InvalidArgumentException("Not enough {$item->product->name} in stock for this order. Nothing was handed over.");
                }
            }

            // 2. The rebate, from the ledger that funded the order.
            $this->rebate($store, $order);

            $order->forceFill(['status' => Order::FULFILLED, 'fulfilled_at' => now(), 'fulfilled_by_store_id' => $store->id])->save();

            return $order;
        });
    }

    private function find(Store $store, string $code): Order
    {
        if (! $store->isActive()) {
            throw new InvalidArgumentException('This store is not active, so it cannot hand over orders.');
        }

        $order = $this->orders->findByCode($code);

        return match (true) {
            $order === null => throw new InvalidArgumentException('No order has that code. Check it and try again.'),
            $order->status === Order::FULFILLED => throw new InvalidArgumentException("Order {$order->number} was already collected on {$order->fulfilled_at->toDayDateTimeString()}."),
            $order->status !== Order::PAID => throw new InvalidArgumentException("Order {$order->number} is not paid."),
            default => $order->load('items.product', 'user'),
        };
    }

    private function rate(Store $store): int
    {
        return config('pools.schedule.stockist_rebate')[$store->kind];
    }

    private function rebate(Store $store, Order $order): void
    {
        $rate = $this->rate($store);
        $wallet = $this->ledger->storeWallet($store);
        $memo = "Rebate: {$order->units} units of order {$order->number}";

        if ($order->kind === Order::PUBLIC) {
            // Public sales keep the largest rebate aside; what a Sub store does not use is the manufacturer's.
            $held = $this->ledger->poolAccount('P', 'stockist');
            $max = max(config('pools.schedule.stockist_rebate'));
            $lines = [[$held, -$order->units * $max], [$wallet, $order->units * $rate]];
            if ($max > $rate) {
                foreach ($order->items->groupBy('manufacturer_id') as $makerId => $items) {
                    $lines[] = [$this->ledger->manufacturerAccount(Manufacturer::findOrFail($makerId)), $items->sum('quantity') * ($max - $rate)];
                }
            }
            $this->ledger->post('payout.stockist_rebate', 'rebate:'.$order->id, $lines, $memo, $order);

            return;
        }

        $pool = self::POOL[$order->kind];
        $this->payouts->pay($pool, 'stockist_rebate', $wallet, $order->units * $rate, 'stockist_rebate', 'rebate:'.$order->id, $memo, $order);

        // What a Sub store does not take of the line's ₦120 a unit goes to the pool's contingency fund (section 7).
        $rest = $order->units * (max(config('pools.schedule.stockist_rebate')) - $rate);
        $line = $this->ledger->poolAccount($pool, 'stockist_rebate');
        if ($pool !== 'E' && $rest > 0 && $this->ledger->balance($line) >= $rest) {
            $this->payouts->toContingency($pool, 'stockist_rebate', $rest, 'rebate-rest:'.$order->id, "Unused rebate on order {$order->number} (Sub store)", $order);
        }
    }
}
