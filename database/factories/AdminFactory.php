<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->regexify('[a-z]{8}'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    public function super(): static
    {
        return $this->afterCreating(fn (Admin $admin) => $admin->roles()->attach(Role::super()));
    }

    /** @param  list<string>  $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(function (Admin $admin) use ($permissions) {
            $role = Role::create(['name' => 'Role '.fake()->unique()->numberBetween(1, 1_000_000), 'permissions' => $permissions]);
            $admin->roles()->attach($role);
        });
    }

    public function deactivated(): static
    {
        return $this->afterCreating(fn (Admin $admin) => $admin->forceFill(['is_active' => false])->save());
    }
}
