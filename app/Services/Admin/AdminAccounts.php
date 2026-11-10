<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Adding and changing admins and roles (specification section 11). Every
 * change is audited in the same transaction. Two safety rules:
 *
 *  - there is always at least one active super-admin, so the panel can never
 *    be locked with nobody able to manage it;
 *  - an admin cannot deactivate themselves.
 */
class AdminAccounts
{
    public function __construct(private readonly Audit $audit) {}

    /**
     * @param  array{name: string, username: string, email: string, password: string}  $data
     * @param  list<int>  $roleIds
     */
    public function create(Admin $by, array $data, array $roleIds): Admin
    {
        $this->requireRoles($roleIds);

        return DB::transaction(function () use ($by, $data, $roleIds) {
            $admin = Admin::create([...$data, 'email' => strtolower($data['email'])]);
            $admin->roles()->sync($roleIds);

            $this->audit->record($by, 'admins.create', $admin, null, [
                'name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email, 'roles' => $this->roleNames($roleIds),
            ]);

            return $admin;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: ?string}  $data
     * @param  list<int>  $roleIds
     */
    public function update(Admin $by, Admin $admin, array $data, array $roleIds): void
    {
        $this->requireRoles($roleIds);

        DB::transaction(function () use ($by, $admin, $data, $roleIds) {
            $this->lockSupers();

            $oldRoles = $admin->roles()->pluck('roles.id')->sort()->values()->all();
            $newRoles = collect($roleIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($admin->isSuper() && ! in_array(Role::super()->id, $newRoles, true)) {
                $this->ensureAnotherActiveSuper($admin, 'This is the only active super-admin. Make another admin a super-admin first.');
            }

            $admin->fill(['name' => $data['name'], 'email' => strtolower($data['email'])]);
            if (! empty($data['password'])) {
                $admin->password = $data['password'];
            }

            $new = array_intersect_key($admin->getDirty(), array_flip(['name', 'email']));
            $old = array_intersect_key($admin->getOriginal(), $new);
            if ($admin->isDirty('password')) {
                $new['password'] = '(changed)'; // never the password itself
            }

            if ($oldRoles !== $newRoles) {
                $old['roles'] = $this->roleNames($oldRoles);
                $new['roles'] = $this->roleNames($newRoles);
            }

            if ($new === []) {
                return;
            }

            $admin->save();
            $admin->roles()->sync($newRoles);
            $admin->unsetRelation('roles');

            $this->audit->record($by, 'admins.edit', $admin, $old, $new);
        });
    }

    public function deactivate(Admin $by, Admin $admin, string $reason): void
    {
        $reason = $this->reason($reason);

        if ($by->is($admin)) {
            throw new InvalidArgumentException('You cannot deactivate your own account.');
        }
        if (! $admin->isActive()) {
            throw new InvalidArgumentException('This admin is already deactivated.');
        }

        DB::transaction(function () use ($by, $admin, $reason) {
            $this->lockSupers();

            if ($admin->isSuper()) {
                $this->ensureAnotherActiveSuper($admin, 'This is the only active super-admin, so it cannot be deactivated.');
            }

            $admin->forceFill(['is_active' => false])->save();
            $this->audit->record($by, 'admins.deactivate', $admin, ['is_active' => true], ['is_active' => false], $reason);
        });
    }

    public function activate(Admin $by, Admin $admin, string $reason): void
    {
        $reason = $this->reason($reason);

        if ($admin->isActive()) {
            throw new InvalidArgumentException('This admin is already active.');
        }

        DB::transaction(function () use ($by, $admin, $reason) {
            $admin->forceFill(['is_active' => true])->save();
            $this->audit->record($by, 'admins.activate', $admin, ['is_active' => false], ['is_active' => true], $reason);
        });
    }

    /** @param  list<string>  $permissions */
    public function saveRole(Admin $by, ?Role $role, string $name, ?string $description, array $permissions): Role
    {
        if ($role?->is_super) {
            throw new InvalidArgumentException('The super-admin role always has every permission and cannot be changed.');
        }

        return DB::transaction(function () use ($by, $role, $name, $description, $permissions) {
            $isNew = $role === null;
            $role ??= new Role;
            $old = $isNew ? null : ['name' => $role->name, 'description' => $role->description, 'permissions' => $role->permissions];

            $role->fill(['name' => $name, 'description' => $description, 'permissions' => array_values($permissions)]);
            $role->save(); // the model refuses unknown and super-only permissions

            $new = ['name' => $role->name, 'description' => $role->description, 'permissions' => $role->permissions];
            if ($old !== null) {
                $changed = array_keys(array_filter($new, fn ($value, $key) => $old[$key] !== $value, ARRAY_FILTER_USE_BOTH));
                if ($changed === []) {
                    return $role;
                }
                $old = array_intersect_key($old, array_flip($changed));
                $new = array_intersect_key($new, array_flip($changed));
            }

            $this->audit->record($by, $isNew ? 'roles.create' : 'roles.edit', $role, $old, $new);

            return $role;
        });
    }

    public function deleteRole(Admin $by, Role $role): void
    {
        if ($role->is_super) {
            throw new InvalidArgumentException('The super-admin role cannot be deleted.');
        }
        if ($role->admins()->exists()) {
            throw new InvalidArgumentException('Admins still have this role. Give them another role first.');
        }

        DB::transaction(function () use ($by, $role) {
            $this->audit->record($by, 'roles.delete', $role, ['name' => $role->name, 'permissions' => $role->permissions], null);
            $role->delete();
        });
    }

    /** @param  list<int>  $roleIds */
    private function requireRoles(array $roleIds): void
    {
        if ($roleIds === [] || Role::whereIn('id', $roleIds)->count() !== count(array_unique($roleIds))) {
            throw new InvalidArgumentException('Choose at least one role.');
        }
    }

    /** Lock the super-admins' rows, so two changes at once cannot both remove "the other" super-admin. */
    private function lockSupers(): void
    {
        Admin::whereHas('roles', fn ($q) => $q->where('is_super', true))->lockForUpdate()->get(['id']);
    }

    private function ensureAnotherActiveSuper(Admin $admin, string $message): void
    {
        $others = Admin::where('is_active', true)->whereKeyNot($admin->id)
            ->whereHas('roles', fn ($q) => $q->where('is_super', true))->exists();

        if (! $others) {
            throw new InvalidArgumentException($message);
        }
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason (for the audit log).');
        }

        return mb_substr($reason, 0, 250);
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function roleNames(array $ids): array
    {
        return Role::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }
}
