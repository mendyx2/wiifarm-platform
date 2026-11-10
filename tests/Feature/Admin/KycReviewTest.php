<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\KycSubmission;
use App\Models\User;
use App\Services\Members\Kyc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * KYC review by admins (specification sections 2 and 11).
 */
class KycReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Kyc::DISK);
    }

    private function submission(?User $member = null): KycSubmission
    {
        return app(Kyc::class)->submit($member ?? User::factory()->create(), [
            'nin' => '12345678901',
            'id_type' => 'National ID card',
            'id_front' => UploadedFile::fake()->image('id.jpg'),
            'selfie' => UploadedFile::fake()->image('me.png'),
        ]);
    }

    public function test_the_list_shows_waiting_submissions_oldest_first()
    {
        $first = $this->submission();
        $second = $this->submission();

        $this->actingAs(Admin::factory()->withPermissions(['kyc.view'])->create(), 'admin')
            ->get('/admin/kyc')
            ->assertInertia(fn ($page) => $page
                ->component('admin/kyc/index')
                ->where('submissions.data.0.id', $first->id)
                ->where('submissions.data.1.id', $second->id));
    }

    public function test_an_admin_with_kyc_view_can_see_the_documents()
    {
        $submission = $this->submission();

        $this->actingAs(Admin::factory()->withPermissions(['kyc.view'])->create(), 'admin');
        $this->get("/admin/kyc/{$submission->id}")->assertOk();
        $this->get("/admin/kyc/{$submission->id}/files/id_front")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get("/admin/kyc/{$submission->id}/files/nin")->assertNotFound();
    }

    public function test_documents_need_permission()
    {
        $submission = $this->submission();

        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')
            ->get("/admin/kyc/{$submission->id}/files/id_front")->assertForbidden();

        // and members never reach the admin route at all
        $this->app['auth']->forgetGuards();
        $this->actingAs($submission->user, 'web');
        $this->get("/admin/kyc/{$submission->id}/files/id_front")->assertRedirect('/admin/login');
    }

    public function test_approving_verifies_the_member_and_is_logged()
    {
        $submission = $this->submission();
        $admin = Admin::factory()->withPermissions(['kyc.view', 'kyc.review'])->create();

        $this->actingAs($admin, 'admin')->post("/admin/kyc/{$submission->id}/approve")->assertRedirect('/admin/kyc');

        $this->assertSame('verified', $submission->user->fresh()->kyc_status);
        $this->assertSame($admin->id, $submission->fresh()->reviewed_by_admin_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kyc.approve', 'subject_type' => 'KycSubmission', 'subject_id' => $submission->id]);

        // a second decision on the same submission is refused
        $this->post("/admin/kyc/{$submission->id}/reject", ['reason' => 'Too late'])->assertSessionHasErrors('kyc');
        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame(1, AuditLog::where('subject_type', 'KycSubmission')->count());
    }

    public function test_rejecting_needs_a_reason_the_member_will_see()
    {
        $submission = $this->submission();
        $this->actingAs(Admin::factory()->withPermissions(['kyc.view', 'kyc.review'])->create(), 'admin');

        $this->post("/admin/kyc/{$submission->id}/reject", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $submission->fresh()->status);

        $this->post("/admin/kyc/{$submission->id}/reject", ['reason' => 'The ID photo is blurred.']);

        $this->assertSame('unverified', $submission->user->fresh()->kyc_status);
        $this->assertSame('The ID photo is blurred.', $submission->fresh()->rejection_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kyc.reject', 'note' => 'The ID photo is blurred.']);
    }

    public function test_viewing_alone_cannot_decide()
    {
        $submission = $this->submission();

        $this->actingAs(Admin::factory()->withPermissions(['kyc.view'])->create(), 'admin')
            ->post("/admin/kyc/{$submission->id}/approve")->assertForbidden();

        $this->assertSame('pending', $submission->fresh()->status);
    }
}
