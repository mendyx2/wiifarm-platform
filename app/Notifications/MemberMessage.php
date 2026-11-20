<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One member message, already filled in (the Messages service). It is sent
 * in the background, once the change it reports has been saved.
 */
class MemberMessage extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array{label: string, url: string}|null  $button */
    public function __construct(
        public readonly int $logId,
        public readonly string $event,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?array $button = null,
        public readonly ?string $name = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting(trim('Hello '.$this->name).',');
        foreach (preg_split('/\R\s*\R/', trim($this->body)) as $paragraph) {
            $mail->line(trim($paragraph));
        }
        if ($this->button !== null) {
            $mail->action($this->button['label'], $this->button['url']);
        }

        return $mail;
    }
}
