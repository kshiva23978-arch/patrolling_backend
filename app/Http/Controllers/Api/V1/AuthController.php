<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'employee_id' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'password_hash' => ['nullable', 'string'],
        ]);

        $employeeId = trim((string) $request->input('employee_id'));
        $password = $request->input('password');
        $providedHash = $request->input('password_hash');

        if ($request->filled('password_hash') && empty($password)) {
            $password = $providedHash;
        }

        if ($employeeId === '' || (empty($password) && empty($providedHash))) {
            throw ValidationException::withMessages([
                'employee_id' => ['The employee ID and password are required.'],
                'password' => ['The employee ID and password are required.'],
            ]);
        }

        $user = User::where('u_employee_id', $employeeId)->first();

        if (! $user || ! $this->verifyPassword($user, $password, $providedHash)) {
            throw ValidationException::withMessages([
                'employee_id' => ['Invalid employee ID or password.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'employee_id' => $user->u_employee_id,
                'role' => $user->u_role_id,
                'token' => $token,
            ],
        ]);
    }

    protected function verifyPassword(User $user, ?string $password, ?string $providedHash): bool
    {
        $storedHash = (string) $user->u_password_hash;

        if (str_starts_with($storedHash, '$2') || str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$2a$')) {
            $candidate = $providedHash ?? $password;

            return Hash::check($candidate, $storedHash);
        }

        $candidate = $providedHash ?? $password;

        return hash_equals($storedHash, (string) $candidate);
    }
}
