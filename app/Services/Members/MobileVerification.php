<?php

namespace App\Services\Members;

use App\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Confirms that a member owns their mobile number: a 6-digit code is sent by
 * SMS and typed back. Only the code's hash is stored; it expires, and too many
 * wrong tries void it.
 */
class MobileVerification
{
    public function __construct(private readonly SmsSender $sms) {}

    public function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        $number = $user->dial_code.$user->mobile;

        DB::table('mobile_codes')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'mobile' => $number,
                'expires_at' => now()->addMinutes(config('wiifarm.mobile_code_minutes')),
                'attempts' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->sms->send($number, "Your Wiifarm code is {$code}. It expires in ".config('wiifarm.mobile_code_minutes').' minutes. Do not share it.');
    }

    /**
     * @return string|null null when verified, otherwise the reason it was not
     */
    public function verify(User $user, string $code): ?string
    {
        $row = DB::table('mobile_codes')->where('user_id', $user->id)->first();

        if (! $row) {
            return 'Request a code first.';
        }
        if ($row->mobile !== $user->dial_code.$user->mobile) {
            return 'Your number changed since the code was sent. Request a new code.';
        }
        if (now()->greaterThan($row->expires_at)) {
            return 'That code has expired. Request a new one.';
        }
        if ($row->attempts >= config('wiifarm.mobile_code_attempts')) {
            return 'Too many wrong codes. Request a new one.';
        }
        if (! Hash::check(trim($code), $row->code_hash)) {
            DB::table('mobile_codes')->where('user_id', $user->id)->increment('attempts');

            return 'That code is not right. Check the message and try again.';
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
            DB::table('mobile_codes')->where('user_id', $user->id)->delete();
        });

        return null;
    }
}
