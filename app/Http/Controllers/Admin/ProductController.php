<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Pools\Manufacturer;
use App\Models\Shop\Product;
use App\Services\Admin\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Products and their prices and PV (specification section 8). Every change is audited. */
class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/products/index', [
            'products' => Product::with('manufacturer:id,name')->orderBy('name')->get()->map(fn (Product $p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'manufacturer' => $p->manufacturer->name,
                'member_price' => $p->member_price, 'public_price' => $p->public_price, 'pv' => $p->pv,
                'units_per_pack' => $p->units_per_pack, 'is_active' => $p->is_active,
                'is_pack_product' => $p->sku === config('wiifarm.registration_product_sku'),
                'image' => $p->imageUrl(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/form', ['product' => null, 'manufacturers' => $this->manufacturers()]);
    }

    public function store(Request $request, Audit $audit): RedirectResponse
    {
        $data = $this->validated($request, null);
        $product = Product::create($data);
        $audit->record($this->admin($request), 'products.create', $product, null, $data);

        return to_route('admin.products.edit', $product)->with('status', 'Product added.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/form', [
            'product' => [
                'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'manufacturer_id' => $product->manufacturer_id,
                'description' => $product->description ?? '',
                'member_price' => number_format($product->member_price / 100, 2, '.', ''),
                'public_price' => number_format($product->public_price / 100, 2, '.', ''),
                'pv' => number_format($product->pv / 100, 2, '.', ''),
                'units_per_pack' => $product->units_per_pack, 'is_active' => $product->is_active,
                'image' => $product->imageUrl(),
            ],
            'manufacturers' => $this->manufacturers(),
        ]);
    }

    public function update(Request $request, Product $product, Audit $audit): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $product->fill($data);
        $changed = $product->getDirty();
        if ($changed !== []) {
            $old = array_intersect_key($product->getOriginal(), $changed);
            $product->save();
            $audit->record($this->admin($request), 'products.edit', $product, $old, $changed);
        }

        return back()->with('status', 'Product saved. New prices apply to orders from now on.');
    }

    /** A photo for the shop: JPEG, PNG or WebP, up to 3 MB. It replaces any earlier photo. */
    public function photo(Request $request, Product $product, Audit $audit): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=200,min_height=200']], [
            'photo.dimensions' => 'Use a photo at least 200 pixels wide and high.',
        ]);
        $old = $product->image_path;
        $path = $request->file('photo')->store('products', 'public');
        $product->forceFill(['image_path' => $path])->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }
        $audit->record($this->admin($request), 'products.photo', $product, ['image_path' => $old], ['image_path' => $path]);

        return back()->with('status', 'Photo saved. It shows in the shop now.');
    }

    public function removePhoto(Request $request, Product $product, Audit $audit): RedirectResponse
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $audit->record($this->admin($request), 'products.photo', $product, ['image_path' => $product->image_path], ['image_path' => null]);
            $product->forceFill(['image_path' => null])->save();
        }

        return back()->with('status', 'Photo removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Product $product): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'manufacturer_id' => ['required', 'integer', 'exists:manufacturers,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'member_price' => ['required', 'string'],
            'public_price' => ['required', 'string'],
            'pv' => ['required', 'string'],
            'units_per_pack' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        // Prices in naira and PV both take up to two decimals: ₦1,500 = 150,000 kobo, 1.5 PV = 150.
        foreach (['member_price', 'public_price', 'pv'] as $field) {
            try {
                $data[$field] = Money::parseNaira($data[$field]);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([$field => 'Enter a number with up to two decimals, e.g. 1500 or 1.5']);
            }
        }
        if ($data['pv'] === 0) {
            throw ValidationException::withMessages(['pv' => 'A product needs some PV.']);
        }
        if ($data['public_price'] < $data['member_price']) {
            throw ValidationException::withMessages(['public_price' => 'The public price cannot be below the member price.']);
        }
        $data['sku'] = strtoupper($data['sku']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** @return list<array{id: int, name: string}> */
    private function manufacturers(): array
    {
        return Manufacturer::orderBy('name')->get(['id', 'name'])->toArray();
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
