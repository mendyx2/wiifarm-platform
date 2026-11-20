<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A notice on the member's dashboard (the bell): a bonus paid, or a copy of
 * a member message (specification section 12). It is saved at once, with the
 * change it reports, so it never appears for something that did not happen.
 */
class InAppNotice extends Notification
{
    public function __construct(
        public readonly string $kind,      // bonus, message
        public readonly string $title,
        public readonly ?string $text = null,
        public readonly ?int $amount = null,
        public readonly ?string $url = null,
    ) {
        $this->id = (string) Str::orderedUuid();   // sorts by time, so the newest come first even within a second
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'text' => $this->text, 'amount' => $this->amount, 'url' => $this->url];
    }
}
