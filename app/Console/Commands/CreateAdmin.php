<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Role;
use App\Services\Admin\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a super-admin from the server's command line. This is how the first
 * admin is made: there is no default admin account and no default password.
 *
 *     php artisan wiifarm:create-admin
 */
class CreateAdmin extends Command
{
    protected $signature = 'wiifarm:create-admin {--from-env : Take the details from ADMIN_NAME, ADMIN_USERNAME, ADMIN_EMAIL and ADMIN_PASSWORD (for a preview site); does nothing if that admin exists}';

    protected $description = 'Create a super-admin account (asks for the details; there is no default password)';

    public function handle(Audit $audit): int
    {
        if ($this->option('from-env')) {
            $data = ['name' => env('ADMIN_NAME', 'Preview admin'), 'username' => env('ADMIN_USERNAME'), 'email' => env('ADMIN_EMAIL'), 'password' => env('ADMIN_PASSWORD')];
            $data['password_confirmation'] = $data['password'];
            if ($data['username'] && Admin::where('username', $data['username'])->exists()) {
                $this->info("Admin {$data['username']} already exists.");

                return self::SUCCESS;
            }
        } else {
            $data = [
                'name' => $this->ask('Full name'),
                'username' => $this->ask('Username (letters, digits, underscores)'),
                'email' => $this->ask('Email address'),
                'password' => $this->secret('Password (at least 12 characters)'),
            ];
            $data['password_confirmation'] = $this->secret('Type the password again');
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'regex:/^[A-Za-z0-9_]{4,40}$/', 'unique:admins,username'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = DB::transaction(function () use ($data, $audit) {
            $admin = Admin::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
            ]);
            $admin->roles()->attach(Role::super());
            $audit->record(null, 'admins.create', $admin, null, ['username' => $admin->username, 'roles' => [Role::SUPER]], 'Created from the server command line.');

            return $admin;
        });

        $this->info("Super-admin {$admin->username} created. Sign in at ".route('admin.login'));

        return self::SUCCESS;
    }
}
