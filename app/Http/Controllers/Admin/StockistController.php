<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Shop\Order;
use App\Models\Shop\Product;
use App\Models\Stockists\Store;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Ledger\Ledger;
use App\Services\Stockists\Stock;
use App\Services\Stockists\StoreActivation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Stockist stores (specification section 9): set up, activate, deactivate, and the manufacturer's stock allocation. */
class StockistController extends Controller
{
    public function index(Ledger $ledger): Response
    {
        return Inertia::render('admin/stockists/index', [
            'stores' => Store::with('owner:id,username', 'mega:id,name')->orderByDesc('id')->get()->map(fn (Store $s) => [
                'id' => $s->id, 'name' => $s->name, 'kind' => $s->kind, 'status' => $s->status, 'state' => $s->state,
                'owner' => $s->owner->username, 'mega' => $s->mega?->name,
                'wallet' => $ledger->balance($ledger->storeWallet($s)),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/stockists/form', [
            'megas' => Store::where('kind', Store::MEGA)->where('status', Store::ACTIVE)->orderBy('name')->get(['id', 'name']),
            'kinds' => collect(config('stockists.kinds'))->map(fn ($k, $key) => ['key' => $key, 'name' => $k['name'], 'deposit' => $k['deposit']])->values(),
        ]);
    }

    public function store(Request $request, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'owner' => ['required', 'string'],
            'sponsor' => ['nullable', 'string'],
            'kind' => ['required', Rule::in([Store::MEGA, Store::SUB])],
            'mega_store_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $owner = User::where('username', trim($data['owner']))->first();
        if (! $owner || ! $owner->isActivated() || $owner->isBanned()) {
            throw ValidationException::withMessages(['owner' => 'The stockist must be a member in good standing with a paid package.']);
        }
        if (Store::where('user_id', $owner->id)->exists()) {
            throw ValidationException::withMessages(['owner' => 'This member already runs a store.']);
        }
        // The stockist sponsor: named on the form, or the member's own sponsor (section 9).
        $sponsor = filled($data['sponsor'] ?? null) ? User::where('username', trim($data['sponsor']))->first() : $owner->sponsor;
        if (! $sponsor) {
            throw ValidationException::withMessages(['sponsor' => 'No member has that username.']);
        }
        $mega = null;
        if ($data['kind'] === Store::SUB) {
            $mega = Store::where('kind', Store::MEGA)->where('status', Store::ACTIVE)->find($data['mega_store_id'] ?? 0);
            if (! $mega) {
                throw ValidationException::withMessages(['mega_store_id' => 'A Sub store needs the active Mega store it collects from.']);
            }
        }

        $store = Store::create([
            'user_id' => $owner->id, 'kind' => $data['kind'], 'mega_store_id' => $mega?->id, 'sponsor_id' => $sponsor->id,
            'name' => $data['name'], 'state' => $data['state'], 'address' => $data['address'], 'phone' => $data['phone'],
            'status' => Store::PENDING, 'deposit' => config("stockists.kinds.{$data['kind']}.deposit"),
        ]);
        $audit->record($this->admin($request), 'stockists.create', $store, null, ['owner' => $owner->username, 'kind' => $store->kind, 'name' => $store->name]);

        return to_route('admin.stockists.show', $store)->with('status', 'Store set up. Activate it once the deposit is received.');
    }

    public function show(Store $store, Ledger $ledger, Stock $stock): Response
    {
        $store->load('owner:id,username,first_name,last_name', 'sponsor:id,username', 'mega:id,name', 'subs:id,name,mega_store_id,status');
        $levels = $stock->levels($store);

        return Inertia::render('admin/stockists/show', [
            'store' => [
                'id' => $store->id, 'name' => $store->name, 'kind' => $store->kind, 'label' => $store->label(), 'status' => $store->status,
                'state' => $store->state, 'address' => $store->address, 'phone' => $store->phone,
                'owner' => ['id' => $store->owner->id, 'username' => $store->owner->username, 'name' => $store->owner->name],
                'sponsor' => $store->sponsor->username, 'mega' => $store->mega?->name,
                'subs' => $store->subs->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'status' => $s->status])->values(),
                'deposit' => $store->deposit, 'deposit_reference' => $store->deposit_reference,
                'activated_at' => $store->activated_at?->toDateTimeString(),
            ],
            'wallet' => $ledger->balance($ledger->storeWallet($store)),
            'stock' => Product::orderBy('name')->get()->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'units' => $levels[$p->id] ?? 0]),
            'handovers' => Order::where('fulfilled_by_store_id', $store->id)->latest('fulfilled_at')->limit(20)->get()
                ->map(fn (Order $o) => ['id' => $o->id, 'number' => $o->number, 'kind' => $o->kind, 'units' => $o->units, 'at' => $o->fulfilled_at->toDateTimeString()]),
        ]);
    }

    public function activate(Request $request, Store $store, StoreActivation $activation): RedirectResponse
    {
        $reference = (string) $request->validate(['reference' => ['required', 'string', 'max:191']], ['reference.required' => 'Give the reference of the deposit payment.'])['reference'];

        try {
            $activation->activate($store, $this->admin($request), $reference);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return back()->with('status', 'Store activated. The sponsor bonus and store PV have been paid.');
    }

    public function standing(Request $request, Store $store, Audit $audit): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:250']], ['reason.required' => 'Give a reason (for the audit log).']);
        if ($store->status === Store::PENDING) {
            return back()->withErrors(['reason' => 'Activate the store first.']);
        }

        $new = $data['active'] ? Store::ACTIVE : Store::DEACTIVATED;
        if ($store->status !== $new) {
            $old = $store->status;
            $store->forceFill(['status' => $new])->save();
            $audit->record($this->admin($request), $data['active'] ? 'stockists.reactivate' : 'stockists.deactivate', $store, ['status' => $old], ['status' => $new], $data['reason']);
        }

        return back()->with('status', $data['active'] ? 'Store active again.' : 'Store deactivated. It cannot hand over orders.');
    }

    /** The manufacturing company allocates stock to a Mega store. */
    public function allocate(Request $request, Store $store, Stock $stock): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'units' => ['required', 'integer', 'min:1', 'max:1000000'],
            'form_id' => ['required', 'uuid'],
        ]);

        try {
            $stock->allocate($this->admin($request), $store, Product::findOrFail($data['product_id']), $data['units'], $data['form_id']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['units' => $e->getMessage()]);
        }

        return back()->with('status', "{$data['units']} units allocated to {$store->name}.");
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
