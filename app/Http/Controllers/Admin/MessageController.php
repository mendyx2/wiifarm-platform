<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Messages\MessageTemplate;
use App\Services\Admin\Audit;
use App\Services\Messages\Messages;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Member messages (specification section 12): each message's text and
 * on/off switch, and the log of what was sent. Changes are audited.
 */
class MessageController extends Controller
{
    public function index(Request $request, Messages $messages): Response
    {
        $counts = DB::table('message_log')->where('created_at', '>=', now()->subDays(30))
            ->groupBy('event')->selectRaw('event, COUNT(*) as n')->pluck('n', 'event');
        $event = $request->string('event')->toString();

        return Inertia::render('admin/messages', [
            'emailOn' => (bool) Settings::get('messages.email'),
            'events' => collect(config('messages.events'))->map(fn (array $e, string $key) => [
                'key' => $key, 'name' => $e['name'], 'who' => $e['who'],
                'enabled' => $messages->template($key)['enabled'], 'edited' => $messages->template($key)['edited'],
                'sent30' => (int) ($counts[$key] ?? 0),
            ])->values(),
            'filter' => array_key_exists($event, config('messages.events')) ? $event : null,
            'log' => DB::table('message_log')->leftJoin('users', 'users.id', '=', 'message_log.user_id')
                ->when(array_key_exists($event, config('messages.events')), fn ($q) => $q->where('message_log.event', $event))
                ->orderByDesc('message_log.id')
                ->select('message_log.id', 'message_log.event', 'message_log.email', 'message_log.subject', 'message_log.status', 'message_log.created_at', 'users.username')
                ->paginate(50)->withQueryString(),
        ]);
    }

    public function edit(string $event, Messages $messages): Response
    {
        $default = (config('messages.events')[$event] ?? null) ?? abort(404);

        return Inertia::render('admin/message-edit', [
            'event' => ['key' => $event, 'name' => $default['name'], 'who' => $default['who'], 'placeholders' => $default['placeholders']],
            'template' => $messages->template($event),
            'default' => ['subject' => $default['subject'], 'body' => $default['body']],
        ]);
    }

    public function update(Request $request, string $event, Messages $messages, Audit $audit): RedirectResponse
    {
        (config('messages.events')[$event] ?? null) ?? abort(404);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'enabled' => ['required', 'boolean'],
        ]);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $old = $messages->template($event);

        DB::transaction(function () use ($event, $data, $admin, $audit, $old) {
            $template = MessageTemplate::updateOrCreate(['event' => $event], [
                'subject' => trim($data['subject']), 'body' => trim($data['body']), 'enabled' => (bool) $data['enabled'], 'updated_by_admin_id' => $admin->id,
            ]);
            $audit->record($admin, 'messages.edit', null,
                ['event' => $event, 'subject' => $old['subject'], 'body' => $old['body'], 'enabled' => $old['enabled']],
                ['event' => $event, 'subject' => $template->subject, 'body' => $template->body, 'enabled' => $template->enabled],
                config('messages.events')[$event]['name']);
        });

        return to_route('admin.messages.index')->with('status', 'Message saved.');
    }

    /** Back to the default text (switched on). */
    public function reset(Request $request, string $event, Messages $messages, Audit $audit): RedirectResponse
    {
        (config('messages.events')[$event] ?? null) ?? abort(404);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $old = $messages->template($event);

        DB::transaction(function () use ($event, $admin, $audit, $old) {
            MessageTemplate::where('event', $event)->delete();
            $audit->record($admin, 'messages.reset', null, ['event' => $event, 'subject' => $old['subject'], 'enabled' => $old['enabled']], ['event' => $event], config('messages.events')[$event]['name']);
        });

        return to_route('admin.messages.index')->with('status', 'Message set back to the default.');
    }
}
