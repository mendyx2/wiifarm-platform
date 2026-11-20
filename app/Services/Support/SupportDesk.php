<?php

namespace App\Services\Support;

use App\Models\Admin;
use App\Models\Support\SupportMessage;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Services\Messages\Messages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Support tickets (specification section 11). A member's message makes the
 * ticket "open" (waiting for Wiifarm), an admin's reply makes it "answered"
 * (waiting for the member, who is told). Either side can close it; a member
 * writing again reopens it. Attachments are kept private.
 */
class SupportDesk
{
    public const DISK = 'local';

    public function open(User $member, string $subject, string $body, ?UploadedFile $file = null): SupportTicket
    {
        return DB::transaction(function () use ($member, $subject, $body, $file) {
            $ticket = SupportTicket::create(['user_id' => $member->id, 'subject' => mb_substr(trim($subject), 0, 150), 'status' => SupportTicket::OPEN, 'last_message_at' => now()]);
            $this->add($ticket, null, $body, $file);

            return $ticket;
        });
    }

    public function memberReply(SupportTicket $ticket, string $body, ?UploadedFile $file = null): void
    {
        DB::transaction(function () use ($ticket, $body, $file) {
            $this->add($ticket, null, $body, $file);
            $ticket->forceFill(['status' => SupportTicket::OPEN, 'closed_at' => null, 'last_message_at' => now()])->save();
        });
    }

    public function adminReply(SupportTicket $ticket, Admin $admin, string $body, ?UploadedFile $file = null, bool $close = false): void
    {
        DB::transaction(function () use ($ticket, $admin, $body, $file, $close) {
            $this->add($ticket, $admin, $body, $file);
            $ticket->forceFill(['status' => $close ? SupportTicket::CLOSED : SupportTicket::ANSWERED, 'closed_at' => $close ? now() : null, 'last_message_at' => now()])->save();
            app(Messages::class)->send($ticket->user, 'support.replied', ['subject' => $ticket->subject], route('support.show', $ticket));
        });
    }

    public function close(SupportTicket $ticket): void
    {
        $ticket->forceFill(['status' => SupportTicket::CLOSED, 'closed_at' => now()])->save();
    }

    private function add(SupportTicket $ticket, ?Admin $admin, string $body, ?UploadedFile $file): SupportMessage
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('Write a message.');
        }
        $path = $file?->storeAs('support/'.$ticket->id, Str::uuid().'.'.$file->guessExtension(), self::DISK);

        return SupportMessage::create([
            'ticket_id' => $ticket->id, 'admin_id' => $admin?->id, 'body' => trim($body),
            'attachment_path' => $path, 'attachment_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 150) : null, 'created_at' => now(),
        ]);
    }
}
