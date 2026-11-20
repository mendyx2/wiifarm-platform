<?php

namespace Tests\Feature\Shop;

use App\Models\Admin;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Tests\Feature\Network\NetworkTestCase;

/** Product categories, and browsing both shops by them (specification section 8). */
class CategoriesTest extends NetworkTestCase
{
    public function test_an_admin_manages_categories_and_both_shops_offer_them()
    {
        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')
            ->post('/admin/products/categories', ['name' => 'Nutrition'])->assertForbidden();

        $admin = Admin::factory()->withPermissions(['products.manage'])->create();
        $this->actingAs($admin, 'admin')->post('/admin/products/categories', ['name' => 'Nutrition', 'sort' => 1])->assertSessionHasNoErrors();
        $this->post('/admin/products/categories', ['name' => 'Nutrition'])->assertSessionHasErrors('name');   // names are unique
        $nutrition = ProductCategory::where('name', 'Nutrition')->firstOrFail();

        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        $this->put("/admin/products/{$product->id}", [
            'name' => $product->name, 'sku' => $product->sku, 'manufacturer_id' => $product->manufacturer_id, 'category_id' => $nutrition->id,
            'description' => '', 'member_price' => '1500', 'public_price' => '2000', 'pv' => '1.5', 'units_per_pack' => 10, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame($nutrition->id, $product->fresh()->category_id);
        $this->get('/admin/products')->assertInertia(fn ($page) => $page->where('categories.0.products', 1));

        $this->get('/products')->assertInertia(fn ($page) => $page->where('categories.0.name', 'Nutrition')->where('products.0.category_id', $nutrition->id));
        $this->actingAs($this->join('shopper_one'))->get('/shop')->assertInertia(fn ($page) => $page->where('categories.0.name', 'Nutrition'));

        // Deleting a category keeps its products.
        $this->actingAs($admin, 'admin')->delete("/admin/products/categories/{$nutrition->id}")->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->category_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.category_delete']);
    }
}
