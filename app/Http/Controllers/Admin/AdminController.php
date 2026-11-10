<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use App\Services\Admin\AdminAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Managing admins (specification section 11; super-admins only). Admins are
 * never deleted, so the audit log always knows who did what; they are
 * deactivated instead.
 */
class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/admins/index', [
            'admins' => Admin::with('roles:id,name,is_super')->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (Admin $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'username' => $a->username,
                    'email' => $a->email,
                    'is_active' => $a->is_active,
                    'roles' => $a->roles->pluck('name'),
                    'last_sign_in_at' => $a->last_sign_in_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/admins/form', ['admin' => null, 'roles' => $this->roleOptions()]);
    }

    public function store(Request $request, AdminAccounts $accounts): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'regex:/^[A-Za-z0-9_]{4,40}$/', 'unique:admins,username'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ], ['username.regex' => 'Use 4 to 40 letters, digits or underscores.', 'roles.required' => 'Choose at least one role.']);

        try {
            $admin = $accounts->create($this->by($request), [
                'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'password' => $data['password'],
            ], array_map('intval', $data['roles']));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        return to_route('admin.admins.edit', $admin)->with('status', "Admin @{$admin->username} added. Give them their password in person, not by email.");
    }

    public function edit(Admin $admin): Response
    {
        $admin->load('roles:id');

        return Inertia::render('admin/admins/form', [
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'username' => $admin->username,
                'email' => $admin->email,
                'is_active' => $admin->is_active,
                'roles' => $admin->roles->pluck('id'),
                'last_sign_in_at' => $admin->last_sign_in_at?->toDateTimeString(),
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(Request $request, Admin $admin, AdminAccounts $accounts): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'confirmed', Password::min(12)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ], ['roles.required' => 'Choose at least one role.']);

        try {
            $accounts->update($this->by($request), $admin, $data, array_map('intval', $data['roles']));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        return back()->with('status', 'Admin saved.');
    }

    public function deactivate(Request $request, Admin $admin, AdminAccounts $accounts): RedirectResponse
    {
        try {
            $accounts->deactivate($this->by($request), $admin, (string) $request->input('reason'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Admin deactivated. They are signed out on their next click.');
    }

    public function activate(Request $request, Admin $admin, AdminAccounts $accounts): RedirectResponse
    {
        try {
            $accounts->activate($this->by($request), $admin, (string) $request->input('reason'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Admin active again.');
    }

    /** @return list<array{id: int, name: string, description: ?string, is_super: bool}> */
    private function roleOptions(): array
    {
        Role::super(); // make sure the super-admin role exists

        return Role::orderByDesc('is_super')->orderBy('name')->get(['id', 'name', 'description', 'is_super'])->toArray();
    }

    private function by(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
