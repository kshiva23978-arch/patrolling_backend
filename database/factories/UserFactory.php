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
            'u_id' => (string) Str::uuid(),
            'u_employee_id' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'u_password_hash' => static::$password ??= Hash::make('password'),
            'u_role_id' => (string) Str::uuid(),
            'u_designation_id' => (string) Str::uuid(),
            'u_status' => true,
            'u_last_login' => null,
            'u_created_at' => now(),
            'u_updated_at' => now(),
        ];
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
