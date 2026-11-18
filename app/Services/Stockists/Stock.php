<?php

namespace App\Services\Stockists;

use App\Models\Admin;
use App\Models\Shop\Product;
use App\Models\Stockists\Store;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock movements (specification section 9): the manufacturing company
 * allocates stock to Mega stores; a Sub store collects from its Mega store.
 * Stock is ledger entries in units, so a store can never hand over or pass on
 * more than it holds.
 */
class Stock
{
    public function __construct(private readonly Ledger $ledger, private readonly Audit $audit) {}

    /** The manufacturer allocates units to a Mega store. $formId makes a resent form count once. */
    public function allocate(Admin $admin, Store $mega, Product $product, int $units, string $formId): void
    {
        if ($mega->kind !== Store::MEGA || ! $mega->isActive()) {
            throw new InvalidArgumentException('Stock is allocated to an active Mega store; Sub stores collect from their Mega store.');
        }
        $this->units($units);

        DB::transaction(function () use ($admin, $mega, $product, $units, $formId) {
            $tx = $this->ledger->transfer($this->ledger->stockSystemAccount('supplied', $product), $this->ledger->stockAccount($mega, $product), $units, 'stock.allocation', 'stock:alloc:'.$formId, "{$units} × {$product->name} to {$mega->name}", $mega, $admin->id);
            if (! $tx->wasAlreadyPosted) {
                $this->audit->record($admin, 'stock.allocate', $mega, null, ['product' => $product->sku, 'units' => $units]);
            }
        });
    }

    /** A Sub store collects units from its Mega store; the Mega stockist records it. */
    public function collect(Store $mega, Store $sub, Product $product, int $units, string $formId): void
    {
        if ($sub->kind !== Store::SUB || $sub->mega_store_id !== $mega->id) {
            throw new InvalidArgumentException('A Sub store collects only from its own Mega store.');
        }
        if (! $mega->isActive() || ! $sub->isActive()) {
            throw new InvalidArgumentException('Both stores must be active.');
        }
        $this->units($units);

        $this->ledger->transfer($this->ledger->stockAccount($mega, $product), $this->ledger->stockAccount($sub, $product), $units, 'stock.collection', 'stock:collect:'.$formId, "{$units} × {$product->name} collected by {$sub->name}", $sub);
    }

    /** @return array<int, int> product id => units held */
    public function levels(Store $store): array
    {
        return DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_accounts.holder', 'store:'.$store->id)
            ->where('ledger_accounts.currency', Ledger::UNIT)
            ->groupBy('ledger_accounts.kind')
            ->selectRaw('ledger_accounts.kind as kind, SUM(ledger_entries.amount) as units')
            ->pluck('units', 'kind')
            ->mapWithKeys(fn ($u, $kind) => [(int) substr($kind, 6) => (int) $u])
            ->all();
    }

    private function units(int $units): void
    {
        if ($units <= 0 || $units > 1_000_000) {
            throw new InvalidArgumentException('Enter a number of units from 1 to 1,000,000.');
        }
    }
}
