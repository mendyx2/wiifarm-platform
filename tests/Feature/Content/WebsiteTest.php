<?php

namespace Tests\Feature\Content;

use App\Models\Admin;
use App\Models\Content\Announcement;
use App\Models\Content\SitePage;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Network\NetworkTestCase;

/**
 * The public website, announcements and maintenance mode (specification
 * sections 11 and 12).
 */
class WebsiteTest extends NetworkTestCase
{
    public function test_the_public_pages_show_what_the_admins_wrote_with_search_details_in_the_page()
    {
        $this->get('/')->assertOk()
            ->assertInertia(fn ($page) => $page->component('public/home')->where('page.title', 'Grow with Wiifarm Cooperative')->has('products'))
            ->assertSee('<meta name="description" content="Quality farm products and a member network that rewards you.">', false)
            ->assertSee('<meta property="og:image"', false);

        foreach (['about', 'contact', 'privacy', 'terms'] as $slug) {
            $this->get("/{$slug}")->assertOk()->assertInertia(fn ($page) => $page->component('public/page')->where('page.slug', $slug));
        }
    }

    public function test_an_admin_edits_a_page_and_its_picture()
    {
        Storage::fake('uploads');
        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/website')->assertForbidden();

        $admin = Admin::factory()->withPermissions(['content.manage'])->create();
        $this->actingAs($admin, 'admin')->get('/admin/website')->assertInertia(fn ($page) => $page->has('pages', 5)->where('pages.0.slug', 'home'));
        $this->post('/admin/website/contact', [
            'title' => 'Contact Wiifarm', 'intro' => 'Call or write to us.', 'body' => "## Office\nPlot 5, Abuja\n\n- Phone: 0800 000 0000",
            'seo_title' => 'Contact', 'seo_description' => 'Our office and phone number.', 'image' => UploadedFile::fake()->image('office.jpg'),
        ])->assertSessionHasNoErrors();

        $page = SitePage::where('slug', 'contact')->firstOrFail();
        $this->assertSame('Contact Wiifarm', $page->title);
        Storage::disk('uploads')->assertExists(substr($page->image, strlen('/uploads/')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.page_edit']);
        $this->get('/contact')->assertSee('<meta name="description" content="Our office and phone number.">', false)
            ->assertInertia(fn ($p) => $p->where('page.body', "## Office\nPlot 5, Abuja\n\n- Phone: 0800 000 0000"));

        $old = $page->image;
        $this->actingAs($admin, 'admin')->post('/admin/website/contact', ['title' => 'Contact Wiifarm', 'remove_image' => true]);
        $this->assertNull($page->fresh()->image);
        Storage::disk('uploads')->assertMissing(substr($old, strlen('/uploads/')));
        $this->get('/admin/website/nonsense')->assertNotFound();
    }

    public function test_announcements_show_on_members_dashboards_while_they_run()
    {
        $admin = Admin::factory()->withPermissions(['content.manage'])->create();
        $this->actingAs($admin, 'admin')->post('/admin/announcements', ['title' => 'Stockist week', 'body' => 'Collect your packs early.', 'tone' => 'important', 'is_active' => true])->assertSessionHasNoErrors();
        $this->post('/admin/announcements', ['title' => 'Later', 'body' => 'Not yet.', 'tone' => 'info', 'is_active' => true, 'starts_at' => now()->addDay()->toDateTimeString()]);
        $this->post('/admin/announcements', ['title' => 'Off', 'body' => 'Switched off.', 'tone' => 'info', 'is_active' => false]);

        $this->actingAs($this->join('member_one'))->get('/dashboard')->assertInertia(fn ($page) => $page
            ->has('announcements', 1)->where('announcements.0.title', 'Stockist week')->where('announcements.0.tone', 'important'));

        $first = Announcement::where('title', 'Stockist week')->firstOrFail();
        $this->actingAs($admin, 'admin')->delete("/admin/announcements/{$first->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.announcement_delete']);
        $this->post('/admin/announcements', ['title' => 'Bad', 'body' => 'x', 'tone' => 'info', 'starts_at' => '2026-12-02', 'ends_at' => '2026-12-01'])->assertSessionHasErrors('ends_at');
    }

    public function test_maintenance_mode_closes_the_site_but_not_the_admin_panel_or_payments()
    {
        $member = $this->join('member_one');
        Settings::set('site.maintenance', true);

        $this->get('/')->assertStatus(503)->assertInertia(fn ($page) => $page->component('maintenance'));
        $this->actingAs($member)->get('/dashboard')->assertStatus(503);
        $this->assertNotSame(503, $this->post('/webhooks/paystack')->status());                 // payment confirmations still arrive
        $this->get('/admin/login')->assertOk();

        $admin = Admin::factory()->super()->create();
        $this->actingAs($admin, 'admin')->get('/')->assertOk();                               // admins can still check the site
        $this->put('/admin/settings', ['verification_email_required' => false, 'verification_mobile_required' => false, 'withdrawals_kyc_required' => true,
            'matching_autoship_hold' => true, 'messages_email' => true, 'site_maintenance' => false])->assertSessionHasNoErrors();
        auth('admin')->logout();
        $this->get('/')->assertOk();
    }
}
