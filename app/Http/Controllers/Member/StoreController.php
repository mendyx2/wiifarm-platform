<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\Stockists\Store;
use App\Services\Ledger\InsufficientFunds;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\Statement;
use App\Services\Shop\PickupCode;
use App\Services\Stockists\Handover;
use App\Services\Stockists\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** The stockist's own store: hand orders over by code, see stock and the stockist wallet (specification section 9). */
class StoreController extends Controller
{
    public function index(Request $request, Ledger $ledger, Stock $stock, Statement $statement, ?array $preview = null, ?string $code = null, ?string $error = null): Response
    {
        $store = Store::with('mega:id,name', 'subs:id,name,mega_store_id,status')->where('user_id', $request->user()->id)->first();

        if ($store === null) {
            return Inertia::render('store', ['store' => null]);
        }

        $levels = $stock->levels($store);

        return Inertia::render('store', [
            'store' => [
                'id' => $store->id, 'name' => $store->name, 'kind' => $store->kind, 'label' => $store->label(), 'status' => $store->status,
                'state' => $store->state, 'mega' => $store->mega?->name,
                'subs' => $store->subs->where('status', Store::ACTIVE)->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                'rebate' => config('pools.schedule.stockist_rebate')[$store->kind],
            ],
            'wallet' => $ledger->balance($ledger->storeWallet($store)),
            'statement' => $statement->for($ledger->storeWallet($store), 10),
            'stock' => Product::orderBy('name')->get()->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'units' => $levels[$p->id] ?? 0])->filter(fn ($p) => $p['units'] !== 0 || $store->kind === Store::MEGA)->values(),
            'handovers' => Order::where('fulfilled_by_store_id', $store->id)->latest('fulfilled_at')->limit(10)->get()
                ->map(fn (Order $o) => ['id' => $o->id, 'number' => $o->number, 'units' => $o->units, 'at' => $o->fulfilled_at->toDateTimeString()]),
            'preview' => $preview,
            'code' => $code,
            'lookupError' => $error,
        ]);
    }

    /** The stockist typed a code: show the order before handing it over. */
    public function lookup(Request $request, Handover $handover, Ledger $ledger, Stock $stock, Statement $statement): Response
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:30']])['code'];
        $store = $this->store($request);

        try {
            $preview = $handover->preview($store, $code);
        } catch (InvalidArgumentException $e) {
            return $this->index($request, $ledger, $stock, $statement, null, null, $e->getMessage());
        }

        return $this->index($request, $ledger, $stock, $statement, $preview, implode('-', str_split(PickupCode::normalize($code), 4)));
    }

    public function handover(Request $request, Handover $handover): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:30']])['code'];

        try {
            $order = $handover->fulfil($this->store($request), $code);
        } catch (InvalidArgumentException $e) {
            return to_route('store')->withErrors(['code' => $e->getMessage()]);
        }

        return to_route('store')->with('status', "Order {$order->number} handed over. Your rebate is in your stockist wallet.");
    }

    /** A Mega stockist records stock collected by one of their Sub stores. */
    public function collect(Request $request, Stock $stock): RedirectResponse
    {
        $data = $request->validate([
            'sub_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'units' => ['required', 'integer', 'min:1', 'max:1000000'],
            'form_id' => ['required', 'uuid'],
        ]);
        $mega = $this->store($request);
        $sub = Store::find($data['sub_id']);

        try {
            if ($sub === null) {
                throw new InvalidArgumentException('Choose one of your Sub stores.');
            }
            $stock->collect($mega, $sub, Product::findOrFail($data['product_id']), $data['units'], $data['form_id']);
        } catch (InsufficientFunds) {
            return back()->withErrors(['units' => 'You do not have that many units in stock.']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['units' => $e->getMessage()]);
        }

        return back()->with('status', "{$data['units']} units passed to {$sub->name}.");
    }

    private function store(Request $request): Store
    {
        return Store::where('user_id', $request->user()->id)->firstOrFail();
    }
}
