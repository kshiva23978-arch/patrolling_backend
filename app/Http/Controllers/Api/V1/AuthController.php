<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function adminLogin(Request $request)
    {
        return $this->attemptLogin($request, Admin::class, 'a_employee_id', now()->addHours(8));
    }

    public function appLogin(Request $request)
    {
        return $this->attemptLogin($request, User::class, 'u_employee_id');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
            'data' => null,
        ]);
    }

    protected function attemptLogin(Request $request, string $modelClass, string $employeeIdColumn, ?\DateTimeInterface $expiresAt = null)
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

        $account = $modelClass::where($employeeIdColumn, $employeeId)->first();

        // A staff record a Ranger created purely for record-keeping (see
        // `UserController`/`u_has_login`) has no login of its own, even if
        // it somehow ended up with credentials set — reject before even
        // checking the password so no timing signal distinguishes
        // "wrong password" from "this account can't log in".
        if ($account instanceof User && ! $account->u_has_login) {
            $account = null;
        }

        if (! $account || ! $this->verifyPassword($account, $password, $providedHash)) {
            throw ValidationException::withMessages([
                'employee_id' => ['Invalid employee ID or password.'],
            ]);
        }

        $token = $account->createToken('api-token', ['*'], $expiresAt)->plainTextToken;

        $data = [
            'employee_id' => $account->getAuthIdentifier(),
            'token' => $token,
        ];

        if ($account instanceof User) {
            $data['name'] = $account->details?->ud_fullname;
            // `null` means unrestricted (every feature on) — see
            // `User::getPermissionsAttribute`/`Roles::hasAppFeature`. Sent
            // here (not just via `GET /user`) so the app can gate its own
            // UI (which nav tabs/quick actions to show) from the moment it
            // signs in, without a second round trip.
            $data['permissions'] = $account->permissions;
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => $data,
        ]);
    }

    protected function verifyPassword(Authenticatable $account, ?string $password, ?string $providedHash): bool
    {
        $storedHash = (string) $account->getAuthPassword();

        if (str_starts_with($storedHash, '$2') || str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$2a$')) {
            $candidate = $providedHash ?? $password;

            return Hash::check($candidate, $storedHash);
        }

        $candidate = $providedHash ?? $password;

        return hash_equals($storedHash, (string) $candidate);
    }
}
