<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Member\SupportController as MemberSupport;
use App\Models\Admin;
use App\Models\Support\SupportMessage;
use App\Models\Support\SupportTicket;
use App\Services\Support\SupportDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admins answer support tickets (specification section 11). */
class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::in([SupportTicket::OPEN, SupportTicket::ANSWERED, SupportTicket::CLOSED, 'all'])]])['status'] ?? SupportTicket::OPEN;

        return Inertia::render('admin/support/index', [
            'status' => $status,
            'tickets' => SupportTicket::with('user:id,username')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderBy('last_message_at', $status === SupportTicket::OPEN ? 'asc' : 'desc')
                ->paginate(25)->withQueryString()
                ->through(fn (SupportTicket $t) => MemberSupport::row($t) + ['member' => $t->user->username, 'member_id' => $t->user_id]),
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        return Inertia::render('admin/support/show', [
            'ticket' => MemberSupport::row($ticket) + ['member' => $ticket->user->username, 'member_id' => $ticket->user_id],
            'messages' => $ticket->messages()->with('admin:id,name')->get()->map(fn (SupportMessage $m) => $m->toRow('admin.support.file')),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportDesk $desk): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'file' => MemberSupport::FILE_RULES, 'close' => ['boolean']]);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $desk->adminReply($ticket, $admin, $data['body'], $request->file('file'), $request->boolean('close'));

        return to_route('admin.support.index')->with('status', "Reply sent on ticket #{$ticket->id}.");
    }

    public function close(SupportTicket $ticket, SupportDesk $desk): RedirectResponse
    {
        $desk->close($ticket);

        return to_route('admin.support.index')->with('status', "Ticket #{$ticket->id} closed.");
    }

    public function file(SupportTicket $ticket, SupportMessage $message): StreamedResponse
    {
        abort_unless($message->ticket_id === $ticket->id && $message->attachment_path, 404);

        return Storage::disk(SupportDesk::DISK)->download($message->attachment_path, $message->attachment_name, ['Cache-Control' => 'private, no-store']);
    }
}
