<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportMessage;
use App\Models\Support\SupportTicket;
use App\Services\Support\SupportDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A member's support tickets (specification section 11). Members see only their own. */
class SupportController extends Controller
{
    public const FILE_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    public function index(Request $request): Response
    {
        return Inertia::render('support/index', [
            'tickets' => SupportTicket::where('user_id', $request->user()->id)->latest('last_message_at')->paginate(20)
                ->through(fn (SupportTicket $t) => self::row($t)),
        ]);
    }

    public function store(Request $request, SupportDesk $desk): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'file' => self::FILE_RULES,
        ], ['file.mimes' => 'Attach a photo (JPG, PNG) or a PDF.', 'file.max' => 'The file can be up to 5 MB.']);
        $ticket = $desk->open($request->user(), $data['subject'], $data['body'], $request->file('file'));

        return to_route('support.show', $ticket)->with('status', 'Thank you. We will reply here, and you will be told on your dashboard.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $this->own($request, $ticket);

        return Inertia::render('support/show', [
            'ticket' => self::row($ticket),
            'messages' => $ticket->messages()->with('admin:id,name')->get()->map(fn (SupportMessage $m) => $m->toRow('support.file')),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportDesk $desk): RedirectResponse
    {
        $this->own($request, $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'file' => self::FILE_RULES]);
        $desk->memberReply($ticket, $data['body'], $request->file('file'));

        return back()->with('status', 'Sent.');
    }

    public function close(Request $request, SupportTicket $ticket, SupportDesk $desk): RedirectResponse
    {
        $this->own($request, $ticket);
        $desk->close($ticket);

        return back()->with('status', 'Ticket closed. Write again any time to reopen it.');
    }

    public function file(Request $request, SupportTicket $ticket, SupportMessage $message): StreamedResponse
    {
        $this->own($request, $ticket);
        abort_unless($message->ticket_id === $ticket->id && $message->attachment_path, 404);

        return Storage::disk(SupportDesk::DISK)->download($message->attachment_path, $message->attachment_name, ['Cache-Control' => 'private, no-store']);
    }

    /** @return array<string, mixed> */
    public static function row(SupportTicket $t): array
    {
        return ['id' => $t->id, 'subject' => $t->subject, 'status' => $t->status, 'opened' => $t->created_at->toDateTimeString(), 'last' => $t->last_message_at?->toDateTimeString()];
    }

    private function own(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }
}
