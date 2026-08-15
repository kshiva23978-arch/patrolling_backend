<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in with employee_id and password', function () {
    User::create([
        'u_employee_id' => 'EMP-1001',
        'u_password_hash' => bcrypt('SecurePass@2026'),
        'u_role_id' => 1,
        'u_designation_id' => 1,
        'u_status' => 'active',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'employee_id' => 'EMP-1001',
        'password' => 'SecurePass@2026',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.employee_id', 'EMP-1001')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'employee_id',
                'role',
                'token',
            ],
        ]);
});

it('accepts sha256 + salt password_hash as an alias for password input', function () {
    $salt = 'abcdefghijklmnop';
    $password = 'StrongPass!123';
    $frontendHash = hash('sha256', $salt . $password);

    User::create([
        'u_employee_id' => 'EMP-2002',
        'u_password_hash' => bcrypt($frontendHash),
        'u_role_id' => 2,
        'u_designation_id' => 2,
        'u_status' => 'active',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'employee_id' => 'EMP-2002',
        'password_hash' => $frontendHash,
        'salt' => $salt,
    ]);

    $response->assertOk();
});
