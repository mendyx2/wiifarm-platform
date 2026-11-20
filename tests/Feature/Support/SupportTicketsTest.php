<?php

namespace Tests\Feature\Support;

use App\Models\Admin;
use App\Models\Support\SupportTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Network\NetworkTestCase;

/** Support tickets with private attachments (specification section 11). */
class SupportTicketsTest extends NetworkTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_member_asks_an_admin_replies_and_the_member_is_told()
    {
        $member = $this->join('asker_one');
        $this->actingAs($member)->post('/support', [
            'subject' => 'My pickup code', 'body' => 'The stockist could not find my order.', 'file' => UploadedFile::fake()->image('screen.png'),
        ])->assertSessionHasNoErrors();
        $ticket = SupportTicket::sole();
        $this->assertSame(SupportTicket::OPEN, $ticket->status);
        $message = $ticket->messages()->first();
        $this->get("/support/{$ticket->id}/files/{$message->id}")->assertOk()->assertDownload('screen.png');

        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/support')->assertForbidden();
        $viewer = Admin::factory()->withPermissions(['support.view'])->create();
        $this->actingAs($viewer, 'admin')->get('/admin/support')->assertInertia(fn ($page) => $page->where('tickets.data.0.subject', 'My pickup code'));
        $this->get("/admin/support/{$ticket->id}/files/{$message->id}")->assertOk();
        $this->post("/admin/support/{$ticket->id}/reply", ['body' => 'Hello'])->assertForbidden();

        $admin = Admin::factory()->withPermissions(['support.view', 'support.reply'])->create(['name' => 'Ngozi']);
        $this->actingAs($admin, 'admin')->post("/admin/support/{$ticket->id}/reply", ['body' => 'Your code is on the Orders page.'])->assertRedirect('/admin/support');
        $this->assertSame(SupportTicket::ANSWERED, $ticket->fresh()->status);
        $this->assertDatabaseHas('message_log', ['event' => 'support.replied', 'user_id' => $member->id, 'subject' => 'We replied: My pickup code']);

        $this->actingAs($member)->get("/support/{$ticket->id}")->assertInertia(fn ($page) => $page
            ->has('messages', 2)->where('messages.1.from_wiifarm', true)->where('messages.1.author', 'Wiifarm support'));

        // Closing, then writing again, reopens it.
        $this->post("/support/{$ticket->id}/close");
        $this->assertSame(SupportTicket::CLOSED, $ticket->fresh()->status);
        $this->post("/support/{$ticket->id}/reply", ['body' => 'One more question.']);
        $this->assertSame(SupportTicket::OPEN, $ticket->fresh()->status);
    }

    public function test_members_see_only_their_own_tickets()
    {
        $this->actingAs($this->join('asker_one'))->post('/support', ['subject' => 'Private', 'body' => 'Mine.']);
        $ticket = SupportTicket::sole();
        $message = $ticket->messages()->first();

        $this->actingAs($this->join('curious_two'));
        $this->get("/support/{$ticket->id}")->assertNotFound();
        $this->post("/support/{$ticket->id}/reply", ['body' => 'Hi'])->assertNotFound();
        $this->get("/support/{$ticket->id}/files/{$message->id}")->assertNotFound();
        $this->get('/support')->assertInertia(fn ($page) => $page->has('tickets.data', 0));
        $this->post('/support', ['subject' => 'x', 'body' => 'y', 'file' => UploadedFile::fake()->create('a.exe', 5)])->assertSessionHasErrors('file');
    }
}
