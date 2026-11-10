<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->regexify('[a-z]{6}[0-9]{3}'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'dial_code' => '234',
            'mobile' => fake()->numerify('80########'),
            'country' => 'NG',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** A member whose package has been paid. */
    public function activated(): static
    {
        return $this->state(fn (array $attributes) => ['activated_at' => now()]);
    }

    /** A banned member. */
    public function banned(string $reason = 'Breach of the member terms'): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'banned', 'ban_reason' => $reason]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
