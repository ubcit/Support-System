<?php

namespace Modules\Authentication\Services;

use App\Enums\UserApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate a user and create an API token.
     */
    public function login(string $email, string $password, string $deviceName = 'api'): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isApproved()) {
            $message = $user->isPending()
                ? 'Your account is pending admin approval.'
                : ($user->rejectionMessage() ?: 'Your account was rejected.');

            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Register a new user.
     */
    public function register(array $data, string $deviceName = 'api'): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        app(SignupRequestNotifier::class)->notifyPendingSignup($user);

        return [
            'user' => $user,
            // We intentionally do not issue an API token until the account is approved.
            'token' => null,
        ];
    }

    /**
     * Revoke the current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Revoke all tokens for the user.
     */
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
