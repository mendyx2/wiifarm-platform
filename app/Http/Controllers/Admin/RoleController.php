<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use App\Services\Admin\AdminAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Managing roles (specification section 11; super-admins only). A role is a
 * set of the permissions listed in config/admin_permissions.php.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        Role::super();

        return Inertia::render('admin/roles/index', [
            'roles' => Role::withCount('admins')->orderByDesc('is_super')->orderBy('name')->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'description' => $r->description,
                    'is_super' => $r->is_super,
                    'permissions' => $r->is_super ? count(Role::allPermissions()) : count($r->permissions),
                    'admins' => $r->admins_count,
                ]),
            'total' => count(Role::allPermissions()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/form', ['role' => null] + $this->catalogue());
    }

    public function store(Request $request, AdminAccounts $accounts): RedirectResponse
    {
        $data = $this->validated($request, null);

        try {
            $role = $accounts->saveRole($this->by($request), null, $data['name'], $data['description'] ?? null, $data['permissions'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['permissions' => $e->getMessage()]);
        }

        return to_route('admin.roles.edit', $role)->with('status', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_super' => $role->is_super,
                'permissions' => $role->is_super ? Role::allPermissions() : $role->permissions,
                'admins' => $role->admins()->orderBy('name')->get(['admins.id', 'name', 'username']),
            ],
        ] + $this->catalogue());
    }

    public function update(Request $request, Role $role, AdminAccounts $accounts): RedirectResponse
    {
        $data = $this->validated($request, $role);

        try {
            $accounts->saveRole($this->by($request), $role, $data['name'], $data['description'] ?? null, $data['permissions'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['permissions' => $e->getMessage()]);
        }

        return back()->with('status', 'Role saved. Admins with this role have the new permissions from their next click.');
    }

    public function destroy(Request $request, Role $role, AdminAccounts $accounts): RedirectResponse
    {
        try {
            $accounts->deleteRole($this->by($request), $role);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        return to_route('admin.roles.index')->with('status', 'Role deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Role::allPermissions())],
        ]);
    }

    /** The permission list, grouped, for the checkboxes. @return array<string, mixed> */
    private function catalogue(): array
    {
        return [
            'groups' => collect(config('admin_permissions.groups'))->map(fn (array $permissions, string $group) => [
                'name' => $group,
                'permissions' => collect($permissions)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            ])->values(),
            'superOnly' => config('admin_permissions.super_only'),
        ];
    }

    private function by(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
