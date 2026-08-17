<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
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
            'a_id' => (string) Str::uuid(),
            'a_employee_id' => 'ADM-'.fake()->unique()->numberBetween(1000, 9999),
            'a_password_hash' => static::$password ??= Hash::make('password'),
            'a_role_id' => null,
            'a_designation_id' => null,
            'a_status' => true,
            'a_last_login' => null,
            'a_created_at' => now(),
            'a_updated_at' => now(),
        ];
    }
}
