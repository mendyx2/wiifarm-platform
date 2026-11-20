<?php

namespace App\Services\Messages;

use App\Models\Messages\MessageTemplate;
use App\Models\User;
use App\Notifications\MemberMessage;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

/**
 * Sends member messages by email (specification section 12).
 *
 * A message goes out only when email is switched on for the site and for
 * that message, and the person has an email address. It is sent in the
 * background, after the change it reports is saved, and every message is
 * logged (queued, then sent or failed). Sending a message never stops the
 * action that caused it.
 */
class Messages
{
    /** The message for an event as it will be sent: the admin's version, or the default. @return array{subject: string, body: string, enabled: bool, edited: bool} */
    public function template(string $event): array
    {
        $default = (config('messages.events')[$event] ?? null) ?? throw new InvalidArgumentException("No message for [{$event}].");
        $saved = MessageTemplate::where('event', $event)->first();

        return [
            'subject' => $saved->subject ?? $default['subject'],
            'body' => $saved->body ?? $default['body'],
            'enabled' => $saved->enabled ?? true,
            'edited' => $saved !== null && ($saved->subject !== $default['subject'] || $saved->body !== $default['body']),
        ];
    }

    /** An amount in kobo as members read it: ₦1,234.50. */
    public static function naira(int $kobo): string
    {
        return '₦'.number_format($kobo / 100, 2);
    }

    /** Fills in the {{placeholders}}; unknown ones are left as they are. */
    public static function fill(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/', fn ($m) => array_key_exists($m[1], $data) ? (string) $data[$m[1]] : $m[0], $text);
    }

    /** Sends a message to a member. Returns whether it was sent. */
    public function send(User $to, string $event, array $data = [], ?string $url = null): bool
    {
        return $this->sendTo($to->email, $to, $event, ['name' => $to->first_name] + $data, $url);
    }

    /** Sends a message to an email address, for someone who may not be a member (a public buyer). */
    public function sendTo(?string $email, ?User $user, string $event, array $data = [], ?string $url = null): bool
    {
        try {
            $template = $this->template($event);
            if ($email === null || trim($email) === '' || ! Settings::get('messages.email') || ! $template['enabled']) {
                return false;
            }

            $subject = mb_substr(self::fill($template['subject'], $data), 0, 200);
            $body = self::fill($template['body'], $data);
            $action = (config('messages.events')[$event]['action'] ?? null);
            $button = $action ? ['label' => $action['label'], 'url' => $url ?? route($action['route'])] : null;

            $logId = DB::table('message_log')->insertGetId([
                'event' => $event, 'user_id' => $user?->id, 'email' => $email, 'subject' => $subject,
                'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $message = new MemberMessage($logId, $event, $subject, $body, $button, isset($data['name']) ? (string) $data['name'] : null);
            $user !== null && $user->email === $email ? $user->notify($message) : Notification::route('mail', $email)->notify($message);

            return true;
        } catch (Throwable $e) {
            Log::error('A member message could not be sent', ['event' => $event, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
