<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Notifications\MemberMessage;
use App\Services\Sms\LogSmsSender;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Until an SMS provider is chosen, messages go to the log.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every sign-in, for the login history report (section 11).
        Event::listen(Login::class, function (Login $e) {
            DB::table('login_logs')->insert([
                'guard' => $e->guard, 'account_id' => $e->user->getAuthIdentifier(),
                'name' => (string) ($e->user->username ?? $e->user->code ?? $e->user->email ?? ''),
                'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255), 'created_at' => now(),
            ]);
        });

        // Each member message's log row says whether it went out (section 12).
        Event::listen(NotificationSent::class, function (NotificationSent $e) {
            if ($e->notification instanceof MemberMessage) {
                DB::table('message_log')->where('id', $e->notification->logId)->update(['status' => 'sent', 'updated_at' => now()]);
            }
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $e) {
            if ($e->notification instanceof MemberMessage) {
                DB::table('message_log')->where('id', $e->notification->logId)->update(['status' => 'failed', 'updated_at' => now()]);
            }
        });
    }
}
