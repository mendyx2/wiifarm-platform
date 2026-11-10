<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the log instead of sending them. Used until Wiifarm
 * chooses an SMS provider, and in tests.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('[SMS] to +'.$to.': '.$message);
    }
}
