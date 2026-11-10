<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of admin permissions (specification section 11). The permissions
 * themselves are listed in config/admin_permissions.php.
 *
 * @property int $id
 * @property string $name
 * @property ?string $description
 * @property bool $is_super
 * @property list<string> $permissions
 */
class Role extends Model
{
    public const SUPER = 'Super admin';

    /** @var list<string> */
    protected $fillable = ['name', 'description', 'permissions'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_super' => 'boolean', 'permissions' => 'array'];
    }

    protected static function booted(): void
    {
        // A role can only hold permissions that exist, and only the super-admin
        // role holds the super-only ones. Checked on every save, so no screen
        // or script can get around it.
        static::saving(function (Role $role) {
            $permissions = array_values(array_unique($role->permissions ?? []));

            $unknown = array_diff($permissions, self::allPermissions());
            if ($unknown !== []) {
                throw new \InvalidArgumentException('Unknown permission: '.implode(', ', $unknown).'.');
            }

            if (! $role->is_super && array_intersect($permissions, config('admin_permissions.super_only')) !== []) {
                throw new \InvalidArgumentException('Only the super-admin role can have: '.implode(', ', array_intersect($permissions, config('admin_permissions.super_only'))).'.');
            }

            $role->permissions = $permissions;
        });
    }

    /** @return BelongsToMany<Admin, $this> */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class);
    }

    /** @return list<string> */
    public static function allPermissions(): array
    {
        return collect(config('admin_permissions.groups'))->flatMap(fn (array $group) => array_keys($group))->values()->all();
    }

    /** The one super-admin role, created when it is first needed. */
    public static function super(): self
    {
        $role = static::where('is_super', true)->first();

        if ($role === null) {
            $role = new static(['name' => self::SUPER, 'description' => 'Every permission, including admins, roles and system settings.', 'permissions' => []]);
            $role->is_super = true;
            $role->save();
        }

        return $role;
    }
}
