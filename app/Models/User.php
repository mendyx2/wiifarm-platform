<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A member (specification section 2). Admins are a separate model.
 *
 * @property int $id
 * @property string $username
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property ?int $sponsor_id
 * @property string $status
 * @property ?string $ban_reason
 * @property bool $withdrawals_blocked
 * @property ?string $withdrawals_block_reason
 * @property ?Carbon $activated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BANNED = 'banned';

    /**
     * Usernames: 6 to 40 letters, digits and underscores (specification section 2).
     */
    public const USERNAME_RULE = 'regex:/^[A-Za-z0-9_]{6,40}$/';

    /**
     * Only the fields a member fills in themselves. Sponsor, status, bans and
     * activation are set by the system or an admin, never from a form.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'email',
        'dial_code',
        'mobile',
        'country',
        'state',
        'city',
        'address',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'withdrawals_blocked' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** The full name, for display. */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * The mobile number is kept without the country code, so a leading zero
     * from the local format (0801...) is dropped: +234 0801... is +234 801....
     */
    protected function mobile(): Attribute
    {
        return Attribute::set(fn (string $value) => preg_replace('/^0+/', '', preg_replace('/\D/', '', $value)));
    }

    /** @return BelongsTo<User, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /** The members this member referred (their first generation). @return HasMany<User, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'sponsor_id');
    }

    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    /** A member whose package has been paid (until then the account is pending). */
    public function isActivated(): bool
    {
        return $this->activated_at !== null;
    }

    public static function company(): ?self
    {
        return static::where('username', config('wiifarm.company_username'))->first();
    }
}
