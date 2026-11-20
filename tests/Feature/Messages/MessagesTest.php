<?php

namespace Tests\Feature\Messages;

use App\Models\Admin;
use App\Models\Payments\WithdrawalMethod;
use App\Models\Shop\Product;
use App\Models\User;
use App\Notifications\MemberMessage;
use App\Services\Ledger\Ledger;
use App\Services\Members\Kyc;
use App\Services\Messages\Messages;
use App\Services\Payments\Withdrawals;
use App\Services\Shop\Orders;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Network\NetworkTestCase;

/**
 * Member messages by email (specification section 12).
 */
class MessagesTest extends NetworkTestCase
{
    private function sent(User $to, string $event): ?MemberMessage
    {
        $found = null;
        Notification::assertSentTo($to, MemberMessage::class, function (MemberMessage $m) use ($event, &$found) {
            return $m->event === $event && ($found = $m);
        });

        return $found;
    }

    public function test_joining_welcomes_the_member_and_tells_the_sponsor_and_each_message_is_logged_as_sent()
    {
        $sponsor = $this->join('sponsor_one');                 // sponsored by the company: no message to the company
        $member = $this->join('newbie_one', $sponsor, 'Builder');

        $this->assertDatabaseHas('message_log', ['event' => 'welcome', 'user_id' => $member->id, 'email' => $member->email, 'status' => 'sent']);
        $this->assertDatabaseHas('message_log', ['event' => 'team.new_member', 'user_id' => $sponsor->id, 'subject' => 'A new member joined your team', 'status' => 'sent']);
        $this->assertSame(1, DB::table('message_log')->where('event', 'team.new_member')->count());

        $mail = (new MemberMessage(1, 'team.new_member', 'S', "One.\n\nTwo.", ['label' => 'See my team', 'url' => 'https://x.test/team'], 'Ada'))->toMail($sponsor);
        $this->assertSame(['One.', 'Two.'], $mail->introLines);
        $this->assertSame('Hello Ada,', $mail->greeting);
    }

    public function test_placeholders_are_filled_in()
    {
        Notification::fake();
        $sponsor = $this->join('sponsor_one');
        $member = $this->join('newbie_one', $sponsor, 'Builder');

        $this->assertStringContainsString('@newbie_one has joined your team with the Builder package.', $this->sent($sponsor, 'team.new_member')->body);
        $this->assertSame($member->first_name, $this->sent($member, 'welcome')->name);
        $this->assertSame('Pay {{amount}} to ₦5.00', Messages::fill('Pay {{amount}} to {{ amount2 }}', ['amount2' => '₦5.00']));   // unknown ones stay
    }

    public function test_an_admin_changes_a_message_switches_it_off_and_sets_it_back()
    {
        Notification::fake();
        $viewer = Admin::factory()->withPermissions(['messages.view'])->create();
        $editor = Admin::factory()->withPermissions(['messages.view', 'messages.edit'])->create();

        $this->actingAs(Admin::factory()->withPermissions(['members.view'])->create(), 'admin')->get('/admin/messages')->assertForbidden();
        $this->actingAs($viewer, 'admin')->get('/admin/messages')->assertInertia(fn ($page) => $page
            ->component('admin/messages')->where('emailOn', true)->where('events.0.key', 'welcome')->where('events.0.enabled', true));
        $this->actingAs($viewer, 'admin')->get('/admin/messages/welcome')->assertInertia(fn ($page) => $page
            ->component('admin/message-edit')->where('template.edited', false)->where('event.placeholders', ['name', 'username', 'package']));
        $this->actingAs($viewer, 'admin')->put('/admin/messages/welcome', ['subject' => 'Hi', 'body' => 'x', 'enabled' => true])->assertForbidden();
        $this->actingAs($editor, 'admin')->get('/admin/messages/nonsense')->assertNotFound();

        $this->actingAs($editor, 'admin')->put('/admin/messages/welcome', ['subject' => 'Welcome, {{name}}!', 'body' => 'Your {{package}} is paid.', 'enabled' => true])
            ->assertRedirect('/admin/messages');
        $this->assertDatabaseHas('audit_logs', ['action' => 'messages.edit']);
        $ada = $this->join('ada_first');
        $this->assertSame("Welcome, {$ada->first_name}!", $this->sent($ada, 'welcome')->subject);
        $this->assertSame('Your Pre Starter is paid.', $this->sent($ada, 'welcome')->body);

        $this->actingAs($editor, 'admin')->put('/admin/messages/welcome', ['subject' => 'Welcome', 'body' => 'x', 'enabled' => false]);
        $off = $this->join('bola_second');
        Notification::assertNotSentTo($off, MemberMessage::class, fn ($m) => $m->event === 'welcome');

        $this->actingAs($editor, 'admin')->delete('/admin/messages/welcome')->assertRedirect('/admin/messages');
        $this->assertSame(['subject' => 'Welcome to Wiifarm Cooperative', 'enabled' => true], array_intersect_key(app(Messages::class)->template('welcome'), ['subject' => 1, 'enabled' => 1]));
        $this->assertDatabaseHas('audit_logs', ['action' => 'messages.reset']);
    }

    public function test_nothing_is_sent_when_email_is_switched_off_for_the_site()
    {
        Notification::fake();
        Settings::set('messages.email', false);
        $this->join('quiet_one');

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('message_log')->count());
        $this->actingAs(Admin::factory()->withPermissions(['messages.view'])->create(), 'admin')->get('/admin/messages')
            ->assertInertia(fn ($page) => $page->where('emailOn', false));
    }

    public function test_withdrawals_identity_checks_and_orders_tell_the_member()
    {
        Notification::fake();
        Storage::fake(Kyc::DISK);
        $ledger = app(Ledger::class);
        $member = $this->join('amaka_one');
        $member->forceFill(['kyc_status' => 'verified'])->save();
        $ledger->transfer($ledger->systemAccount('adjustments'), $ledger->memberAccount($member, 'main'), 5_000_000, 'test', 'fund:1');
        $admin = Admin::factory()->create();
        $withdrawals = app(Withdrawals::class);
        $request = fn () => $withdrawals->request('member', $member->id, $ledger->memberAccount($member, 'main'), WithdrawalMethod::firstOrFail(), 1_000_000,
            ['bank' => 'First Bank', 'account_number' => '3012345678', 'account_name' => 'Amaka Okafor'], (string) Str::uuid());

        $withdrawals->approve($request(), $admin, 'NIP-123');
        $paid = $this->sent($member, 'withdrawal.paid');
        $this->assertSame('Your withdrawal of ₦10,000.00 has been paid', $paid->subject);
        $this->assertStringContainsString('reference NIP-123', $paid->body);

        $withdrawals->reject($request(), $admin, 'The account name does not match.');
        $this->assertStringContainsString('The reason: The account name does not match.', $this->sent($member, 'withdrawal.rejected')->body);

        $kyc = app(Kyc::class);
        $checked = $this->join('chidi_two');
        $kyc->reject($kyc->submit($checked, ['nin' => '12345678901', 'id_type' => 'National ID card', 'id_front' => UploadedFile::fake()->image('id.jpg'), 'selfie' => UploadedFile::fake()->image('me.png')]), $admin->id, 'The photo is blurred.');
        $this->assertStringContainsString('The photo is blurred.', $this->sent($checked, 'kyc.rejected')->body);

        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        $order = app(Orders::class)->repurchase($member, [$product->id => 2], 'main', (string) Str::uuid());
        $message = $this->sent($member, 'order.paid');
        $this->assertStringContainsString($order->fresh()->pickup_code, $message->body);
        $this->assertSame(route('orders.index'), $message->button['url']);
    }

    public function test_a_public_buyer_gets_their_pickup_code_with_a_working_link_to_their_order()
    {
        Notification::fake();
        $orders = app(Orders::class);
        $product = Product::where('sku', 'WF-PACK')->firstOrFail();
        $order = $orders->publicOrder([$product->id => 1], ['name' => 'Kemi Ade', 'phone' => '0803', 'email' => 'kemi@example.com']);
        $orders->confirmPublicPayment($order, 'ref_1');

        $message = null;
        Notification::assertSentOnDemand(MemberMessage::class, function (MemberMessage $m, array $channels, $notifiable) use (&$message) {
            return $notifiable->routes['mail'] === 'kemi@example.com' && $m->event === 'order.paid' && ($message = $m);
        });
        $this->assertSame('Kemi Ade', $message->name);
        $this->assertStringContainsString($order->fresh()->pickup_code, $message->body);
        $this->get($message->button['url'])->assertOk();
    }
}
