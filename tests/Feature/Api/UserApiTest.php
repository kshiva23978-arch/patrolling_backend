<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns users for authenticated API requests using the custom schema', function () {
    $user = User::factory()->create([
        'u_employee_id' => 'EMP-1001',
        'u_password_hash' => bcrypt('secret123'),
    ]);

    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/users');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.employee_id', 'EMP-1001');
});
