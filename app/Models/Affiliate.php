<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An affiliate (specification section 8): anyone who shares Wiifarm's public
 * shop, member or not. Separate from members: their own sign-in and wallet.
 *
 * @property int $id
 * @property string $code
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $status
 * @property ?string $ban_reason
 */
class Affiliate extends Authenticatable
{
    public const ACTIVE = 'active';

    public const BANNED = 'banned';

    /** @var list<string> */
    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'password'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** A new affiliate is active until banned. @var array<string, mixed> */
    protected $attributes = ['status' => self::ACTIVE];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isBanned(): bool
    {
        return $this->status === self::BANNED;
    }

    /** A new, unused referral code: "WII-" and six letters and digits that cannot be mistaken for each other. */
    public static function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = 'WII-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
