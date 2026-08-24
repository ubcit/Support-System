<?php

namespace App\Livewire\Auth;

use App\Enums\UserApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Sign Up')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50', 'unique:users,phone', 'unique:employees,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many sign up attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($key, 60);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        app(\Modules\Authentication\Services\SignupRequestNotifier::class)
            ->notifyPendingSignup($user);

        RateLimiter::clear($key);

        session()->flash('status', 'Account created. Please wait for admin approval.');

        // Do not log the user in. They can only sign in after approval.
        return $this->redirectRoute('login', navigate: true);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
