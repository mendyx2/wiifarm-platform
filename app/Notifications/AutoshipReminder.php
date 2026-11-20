<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Make your autoship for the month" (specification 5.6). */
class AutoshipReminder extends Notification
{
    public function __construct(
        public readonly string $month,
        public readonly string $stage,
        public readonly CarbonImmutable $deadline,
        public readonly int $bought,
        public readonly int $needed,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $month = CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')->format('F Y');
        $pv = fn (int $hundredths) => rtrim(rtrim(number_format($hundredths / 100, 2), '0'), '.');
        $deadline = $this->deadline->format('j F');

        return (new MailMessage)
            ->subject($this->stage === 'last_day' ? "Last day for your {$month} autoship" : "Your autoship for {$month}")
            ->greeting('Hello '.($notifiable->first_name ?? '').',')
            ->line("Please make your autoship for {$month}: {$pv($this->needed)} PV of products. So far you have bought {$pv($this->bought)} PV for that month.")
            ->line("You have until {$deadline}. Until you make it, 20% of your matching bonus for the month stays held. If autoship is missed 3 months in a row, the matching held for those months goes to the cooperative's reserve.")
            ->action('Go to the shop', route('shop'));
    }
}
