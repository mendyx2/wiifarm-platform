<?php

namespace App\Services\Members;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor sign-in with an authenticator app (Google Authenticator, Authy,
 * Microsoft Authenticator...). A member turns it on in three steps: start
 * (a secret and a QR code), confirm (type one code from the app), and keep the
 * eight backup codes shown once. Each backup code works one time only.
 */
class TwoFactor
{
    public const BACKUP_CODES = 8;

    public function __construct(private readonly Google2FA $engine = new Google2FA) {}

    public function isOn(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    /** Step 1: a new secret (not yet in force) and its QR code as SVG. */
    public function start(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($this->engine->generateSecretKey(32)),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function secret(User $user): ?string
    {
        return $user->two_factor_secret ? Crypt::decryptString($user->two_factor_secret) : null;
    }

    public function qrSvg(User $user): string
    {
        $url = $this->engine->getQRCodeUrl(config('app.name'), $user->username, (string) $this->secret($user));
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /**
     * Step 2: the first code from the app turns it on.
     *
     * @return list<string>|null the backup codes to show once, or null if the code was wrong
     */
    public function confirm(User $user, string $code): ?array
    {
        $secret = $this->secret($user);
        if (! $secret || $this->isOn($user) || ! $this->engine->verifyKey($secret, $this->clean($code))) {
            return null;
        }

        $codes = $this->newBackupCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $this->storeBackupCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    /**
     * At sign-in: a code from the app, or one unused backup code (which is then used up).
     */
    public function check(User $user, string $code): bool
    {
        if (! $this->isOn($user)) {
            return false;
        }

        $code = trim($code);
        if (preg_match('/^\d{6}$/', $this->clean($code)) && $this->engine->verifyKey((string) $this->secret($user), $this->clean($code))) {
            return true;
        }

        $hashes = json_decode(Crypt::decryptString((string) $user->two_factor_recovery_codes), true) ?: [];
        foreach ($hashes as $i => $hash) {
            if (Hash::check(strtoupper($code), $hash)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($hashes)))])->save();

                return true;
            }
        }

        return false;
    }

    public function backupCodesLeft(User $user): int
    {
        return $this->isOn($user) ? count(json_decode(Crypt::decryptString((string) $user->two_factor_recovery_codes), true) ?: []) : 0;
    }

    /** @return list<string> */
    public function regenerateBackupCodes(User $user): array
    {
        $codes = $this->newBackupCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->storeBackupCodes($codes)])->save();

        return $codes;
    }

    public function turnOff(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    private function clean(string $code): string
    {
        return preg_replace('/\D/', '', $code);
    }

    /** @return list<string> */
    private function newBackupCodes(): array
    {
        return array_map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)), range(1, self::BACKUP_CODES));
    }

    /** @param list<string> $codes */
    private function storeBackupCodes(array $codes): string
    {
        return Crypt::encryptString(json_encode(array_map(fn ($c) => Hash::make($c), $codes)));
    }
}
