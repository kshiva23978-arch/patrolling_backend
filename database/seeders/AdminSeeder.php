<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Seed the first admin account.
     */
    public function run(): void
    {
        if (Admin::where('a_employee_id', 'admin')->exists()) {
            $this->command?->info('Admin "admin" already exists, skipping.');

            return;
        }

        $password = Str::random(12);

        Admin::create([
            'a_employee_id' => 'admin',
            'a_password_hash' => Hash::make($password),
            'a_status' => true,
        ]);

        $this->command?->info("Seeded admin — employee_id: admin, password: {$password}");
        $this->command?->warn('Log in and change this password immediately.');
    }
}
