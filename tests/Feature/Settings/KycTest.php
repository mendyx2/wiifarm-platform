<?php

namespace Tests\Feature\Settings;

use App\Models\KycSubmission;
use App\Models\User;
use App\Services\Members\Kyc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * KYC (specification section 2).
 */
class KycTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Kyc::DISK);
    }

    /** @return array<string, mixed> */
    private function answers(array $overrides = []): array
    {
        return ['answers' => array_merge([
            'nin' => '12345678901',
            'id_type' => 'National ID card',
            'id_front' => UploadedFile::fake()->image('id.jpg'),
            'selfie' => UploadedFile::fake()->image('me.png'),
        ], $overrides)];
    }

    public function test_the_page_shows_the_form_to_an_unverified_member()
    {
        $this->actingAs(User::factory()->create())->get('/settings/kyc')->assertOk();
    }

    public function test_a_member_submits_and_the_files_are_stored_privately()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/kyc', $this->answers())->assertRedirect(route('kyc.edit'));

        $submission = KycSubmission::firstOrFail();
        $this->assertSame('pending', $user->fresh()->kyc_status);
        $this->assertSame('12345678901', $submission->answers['nin']);
        Storage::disk(Kyc::DISK)->assertExists($submission->answers['id_front']);
        $this->assertStringStartsWith('kyc/'.$user->id.'/', $submission->answers['selfie']);
        $this->assertStringNotContainsString('public', $submission->answers['selfie']);
    }

    public function test_answers_are_checked()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/kyc', $this->answers([
            'nin' => '123',
            'id_type' => 'Library card',
            'id_front' => UploadedFile::fake()->create('script.exe', 10),
            'selfie' => null,
        ]))->assertSessionHasErrors(['answers.nin', 'answers.id_type', 'answers.id_front', 'answers.selfie']);

        $this->assertSame(0, KycSubmission::count());
        $this->assertSame('unverified', $user->fresh()->kyc_status);
    }

    public function test_no_second_submission_while_one_is_under_review()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/kyc', $this->answers());

        $this->post('/settings/kyc', $this->answers())->assertSessionHasErrors('kyc');
        $this->assertSame(1, KycSubmission::count());
        $this->assertCount(2, Storage::disk(Kyc::DISK)->allFiles(), 'The refused upload leaves no files behind.');
    }

    public function test_approval_verifies_and_rejection_lets_the_member_try_again()
    {
        $kyc = app(Kyc::class);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/kyc', $this->answers());
        $first = KycSubmission::firstOrFail();

        $kyc->reject($first, adminId: 1, reason: 'The ID photo is blurry.');
        $this->assertSame('unverified', $user->fresh()->kyc_status);

        $this->post('/settings/kyc', $this->answers())->assertRedirect();
        $second = KycSubmission::latest('id')->firstOrFail();
        $kyc->approve($second, adminId: 1);

        $this->assertSame('verified', $user->fresh()->kyc_status);
        $this->assertSame('rejected', $first->fresh()->status, 'The earlier submission stays as a record.');

        $this->expectException(InvalidArgumentException::class);
        $kyc->reject($second, adminId: 1, reason: 'Too late');
    }

    public function test_a_rejection_needs_a_reason()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/kyc', $this->answers());

        $this->expectException(InvalidArgumentException::class);
        app(Kyc::class)->reject(KycSubmission::firstOrFail(), adminId: 1, reason: ' ');
    }

    public function test_files_are_only_shown_to_their_owner()
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/settings/kyc', $this->answers());
        $submission = KycSubmission::firstOrFail();

        $this->actingAs($owner)->get(route('kyc.file', [$submission, 'id_front']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($owner)->get(route('kyc.file', [$submission, 'nin']))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('kyc.file', [$submission, 'id_front']))->assertNotFound();

        auth()->logout();
        $this->get(route('kyc.file', [$submission, 'id_front']))->assertRedirect(route('login'));
    }
}
