<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Someone who runs the platform from the admin panel (specification section 11).
 * Admins are separate from members: their own table, sign-in page and guard.
 *
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $email
 * @property bool $is_active
 * @property ?Carbon $last_sign_in_at
 */
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'username', 'email', 'password'];

    /** A new admin is active until deactivated. @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_sign_in_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isSuper(): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->is_super);
    }

    /**
     * Every permission this admin has, from all their roles. A super-admin has
     * every permission that exists.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->isSuper()) {
            return Role::allPermissions();
        }

        return $this->roles->pluck('permissions')->flatten()->unique()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
