<?php

namespace App\Services\Shop;

use App\Models\Affiliate;
use App\Models\Package;
use App\Models\PackageChange;
use App\Models\Pools\Manufacturer;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\User;
use App\Services\Compensation\Unilevel;
use App\Services\Ledger\Ledger;
use App\Services\Network\PvClimb;
use App\Services\Pools\PaymentSplit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orders (specification section 8). Everyone pays through the platform; nobody
 * pays a stockist. A paid order gets a pickup code, and the goods are
 * collected from any stockist with it.
 *
 *  - Registration: the packs of a new member's package, already paid by the
 *    package (the split happens with the package payment).
 *  - Repurchase: a member buys products from their main balance or product
 *    wallet. The money is split into the manufacturer's share and Pool C, the
 *    PV climbs the tree, and the member's own repurchase PV is recorded.
 *  - Public: a non-member buys online; its split stays outside the cooperative.
 */
class Orders
{
    public const WALLETS = ['main', 'product'];

    public function __construct(
        private readonly Ledger $ledger,
        private readonly PaymentSplit $split,
        private readonly PvClimb $climb,
        private readonly Unilevel $unilevel,
    ) {}

    /** The packs that come with a package; created when the package is paid. */
    public function registration(User $member, Package $package, PackageChange $change): Order
    {
        $key = 'package:'.$change->payment_key;
        if ($existing = Order::where('payment_key', $key)->first()) {
            return $existing;
        }

        $product = Product::where('sku', config('wiifarm.registration_product_sku'))->first()
            ?? throw new InvalidArgumentException('The registration pack product is not set up. Run the database seeder.');

        $units = $package->product_packs * $product->units_per_pack;

        return DB::transaction(function () use ($member, $package, $product, $units, $key) {
            $order = $this->create(Order::REGISTRATION, $member, collect([[$product, $units, intdiv($package->price, $units), intdiv($package->pv * 100, $units)]]));
            // A package's PV comes from the package itself (section 4), not from the pack product.
            $order->forceFill(['amount' => $package->price, 'pv' => $package->pv * 100])->save();

            return $this->markPaid($order, 'package', $key);
        });
    }

    /** The extra packs of a higher package, created when an upgrade is paid (board, November 2026). */
    public function upgradePacks(User $member, Package $from, Package $to, PackageChange $change): ?Order
    {
        $key = 'upgrade:'.$change->payment_key;
        if ($existing = Order::where('payment_key', $key)->first()) {
            return $existing;
        }

        $product = Product::where('sku', config('wiifarm.registration_product_sku'))->firstOrFail();
        $units = ($to->product_packs - $from->product_packs) * $product->units_per_pack;
        if ($units <= 0) {
            return null;
        }

        return DB::transaction(function () use ($member, $from, $to, $product, $units, $key) {
            $amount = $to->price - $from->price;
            $pv = ($to->pv - $from->pv) * 100;
            $order = $this->create(Order::UPGRADE, $member, collect([[$product, $units, intdiv($amount, $units), intdiv($pv, $units)]]));
            $order->forceFill(['amount' => $amount, 'pv' => $pv])->save();

            return $this->markPaid($order, 'package', $key);
        });
    }

    /**
     * A member buys products from a wallet. $formId makes a resent form count once.
     *
     * @param  array<int, int>  $quantities  product id => units
     */
    public function repurchase(User $member, array $quantities, string $wallet, string $formId): Order
    {
        if (! in_array($wallet, self::WALLETS, true)) {
            throw new InvalidArgumentException('Choose the main balance or the product wallet.');
        }
        if (! $member->isActivated() || $member->isBanned()) {
            throw new InvalidArgumentException('Only a member with a paid package can buy products.');
        }

        $key = 'wallet:'.$formId;
        if ($existing = Order::where('payment_key', $key)->first()) {
            return $existing;
        }

        $lines = $this->lines($quantities, fn (Product $p) => $p->member_price);

        return DB::transaction(function () use ($member, $lines, $wallet, $key) {
            $order = $this->create(Order::REPURCHASE, $member, $lines);

            // 1. The member pays from their wallet (refused if it is short).
            $this->ledger->transfer($this->ledger->memberAccount($member, $wallet), $this->ledger->systemAccount('external'), $order->amount, 'order.payment', 'order:pay:'.$order->id, "Order {$order->number}", $order);

            // 2. The money is split: each manufacturer's share, and Pool C.
            foreach ($this->byManufacturer($lines) as $makerId => $amount) {
                $this->split->repurchase($amount, Manufacturer::findOrFail($makerId), "order:{$order->id}:m{$makerId}", $order);
            }

            // 3. The PV climbs the tree, and goes on the member's own repurchase PV log.
            $this->climb->credit($member, $order->pv, PvClimb::SOURCE_PURCHASE, 'pv:order:'.$order->id, $order);
            $this->ledger->post('pv.repurchase', 'pv:repurchase:order:'.$order->id, [
                [$this->ledger->systemAccount('pv.issued'), -$order->pv],
                [$this->ledger->repurchasePvAccount($member), $order->pv],
            ], "Order {$order->number}", $order);

            // 4. The unilevel bonus, at once, to 15 generations of the buyer's referral chain (5.10).
            $this->unilevel->forOrder($order, $member);

            return $this->markPaid($order, $wallet, $key);
        });
    }

    /**
     * A public-shop order, waiting for online payment.
     *
     * @param  array<int, int>  $quantities
     * @param  array{name: string, phone: string, email?: ?string}  $buyer
     */
    public function publicOrder(array $quantities, array $buyer, ?Affiliate $affiliate = null): Order
    {
        $order = $this->create(Order::PUBLIC, null, $this->lines($quantities, fn (Product $p) => $p->public_price));
        $order->forceFill([
            'buyer_name' => $buyer['name'], 'buyer_phone' => $buyer['phone'], 'buyer_email' => $buyer['email'] ?? null,
            // The affiliate whose link brought the buyer; a banned affiliate earns nothing.
            'affiliate_id' => $affiliate && ! $affiliate->isBanned() ? $affiliate->id : null,
        ])->save();

        return $order;
    }

    /** The online payment for a public order is confirmed (by the payment gateway, in a later step). */
    public function confirmPublicPayment(Order $order, string $paymentKey): Order
    {
        if ($order->kind !== Order::PUBLIC) {
            throw new InvalidArgumentException('Only a public order is paid online here.');
        }
        if ($order->status === Order::PAID || $order->status === Order::FULFILLED) {
            return $order;
        }

        return DB::transaction(function () use ($order, $paymentKey) {
            $order->load('items');
            $viaAffiliate = $order->affiliate_id !== null;
            foreach ($order->items->groupBy('manufacturer_id') as $makerId => $items) {
                foreach ($items as $item) {
                    $this->split->publicSale($item->quantity, $item->unit_price, Manufacturer::findOrFail($makerId), $viaAffiliate, "public:{$order->id}:i{$item->id}", $order);
                }
            }

            return $this->markPaid($order, 'online', 'online:'.$paymentKey);
        });
    }

    /** The paid order a pickup code belongs to, or null. */
    public function findByCode(string $code): ?Order
    {
        if (strlen(PickupCode::normalize($code)) !== 12) {
            return null;
        }

        return Order::with('items.product')->where('pickup_code_hash', PickupCode::hash($code))->first();
    }

    /**
     * @param  array<int, int>  $quantities
     * @return Collection<int, array{0: Product, 1: int, 2: int, 3: int}> [product, units, unit price, unit pv]
     */
    private function lines(array $quantities, callable $price): Collection
    {
        $quantities = array_filter(array_map('intval', $quantities), fn ($q) => $q > 0);
        if ($quantities === []) {
            throw new InvalidArgumentException('Choose at least one product.');
        }

        $products = Product::where('is_active', true)->whereIn('id', array_keys($quantities))->get()->keyBy('id');
        if ($products->count() !== count($quantities)) {
            throw new InvalidArgumentException('A product in the order is not on sale.');
        }

        return collect($quantities)->map(function (int $units, int $id) use ($products, $price) {
            if ($units > 10_000) {
                throw new InvalidArgumentException('Order at most 10,000 units of a product at a time.');
            }

            return [$products[$id], $units, $price($products[$id]), $products[$id]->pv];
        })->values();
    }

    /** @param  Collection<int, array{0: Product, 1: int, 2: int, 3: int}>  $lines */
    private function create(string $kind, ?User $member, Collection $lines): Order
    {
        $order = Order::create([
            'kind' => $kind,
            'user_id' => $member?->id,
            'status' => Order::PENDING,
            'amount' => $lines->sum(fn ($l) => $l[1] * $l[2]),
            'pv' => $lines->sum(fn ($l) => $l[1] * $l[3]),
            'units' => $lines->sum(fn ($l) => $l[1]),
        ]);
        $order->forceFill(['number' => 'WF'.str_pad((string) $order->id, 7, '0', STR_PAD_LEFT)])->save();

        foreach ($lines as [$product, $units, $unitPrice, $unitPv]) {
            $order->items()->create([
                'product_id' => $product->id, 'manufacturer_id' => $product->manufacturer_id,
                'quantity' => $units, 'unit_price' => $unitPrice, 'unit_pv' => $unitPv,
            ]);
        }

        return $order;
    }

    /**
     * @param  Collection<int, array{0: Product, 1: int, 2: int, 3: int}>  $lines
     * @return array<int, int> manufacturer id => kobo
     */
    private function byManufacturer(Collection $lines): array
    {
        $out = [];
        foreach ($lines as [$product, $units, $unitPrice]) {
            $out[$product->manufacturer_id] = ($out[$product->manufacturer_id] ?? 0) + $units * $unitPrice;
        }

        return $out;
    }

    /** The order is paid: it gets its pickup code. */
    private function markPaid(Order $order, string $paidWith, string $paymentKey): Order
    {
        do {
            $code = PickupCode::generate();
        } while (Order::where('pickup_code_hash', PickupCode::hash($code))->exists());

        $order->forceFill([
            'status' => Order::PAID,
            'paid_with' => $paidWith,
            'payment_key' => $paymentKey,
            'paid_at' => now(),
            'pickup_code' => $code,
            'pickup_code_hash' => PickupCode::hash($code),
            'pickup_code_hint' => substr(PickupCode::normalize($code), -4),
        ])->save();

        return $order;
    }
}
