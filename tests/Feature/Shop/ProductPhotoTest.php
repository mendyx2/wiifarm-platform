<?php

namespace Tests\Feature\Shop;

use App\Models\Admin;
use App\Models\Shop\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Network\NetworkTestCase;

/** Product photos: uploaded by an admin, shown in both shops. */
class ProductPhotoTest extends NetworkTestCase
{
    private function product(): Product
    {
        return Product::where('sku', 'WF-PACK')->firstOrFail();
    }

    public function test_an_admin_uploads_replaces_and_removes_a_photo_and_the_shops_show_it()
    {
        Storage::fake('uploads');
        $product = $this->product();
        $this->actingAs(Admin::factory()->withPermissions(['products.manage'])->create(), 'admin');

        $this->post("/admin/products/{$product->id}/photo", ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->post("/admin/products/{$product->id}/photo", ['photo' => UploadedFile::fake()->image('tiny.jpg', 100, 100)])
            ->assertSessionHasErrors(['photo' => 'Use a photo at least 200 pixels wide and high.']);

        $this->post("/admin/products/{$product->id}/photo", ['photo' => UploadedFile::fake()->image('vitaactive.jpg', 600, 800)])->assertSessionHasNoErrors();
        $first = $product->fresh()->image_path;
        Storage::disk('uploads')->assertExists($first);
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.photo']);

        $this->post("/admin/products/{$product->id}/photo", ['photo' => UploadedFile::fake()->image('better.png', 600, 800)]);
        $second = $product->fresh()->image_path;
        Storage::disk('uploads')->assertMissing($first);   // the old photo is not left behind
        Storage::disk('uploads')->assertExists($second);

        $this->get('/products')->assertInertia(fn ($page) => $page->where('products.0.image', '/uploads/'.$second));
        $this->actingAs($this->join('a_member'))->get('/shop')->assertInertia(fn ($page) => $page->where('products.0.image', '/uploads/'.$second));

        $this->actingAs(Admin::factory()->withPermissions(['products.manage'])->create(), 'admin');
        $this->delete("/admin/products/{$product->id}/photo");
        $this->assertNull($product->fresh()->image_path);
        Storage::disk('uploads')->assertMissing($second);
    }

    public function test_only_admins_who_manage_products_can_change_photos()
    {
        Storage::fake('uploads');
        $product = $this->product();
        $this->actingAs(Admin::factory()->withPermissions(['orders.view'])->create(), 'admin');

        $this->post("/admin/products/{$product->id}/photo", ['photo' => UploadedFile::fake()->image('x.jpg', 600, 600)])->assertForbidden();
        $this->assertNull($product->fresh()->image_path);
    }
}
