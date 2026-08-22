<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public $avatar;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $statusMessage = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->name = $this->name;
        $user->email = $this->email;
        $user->save();

        $this->persistAvatar($user);

        $employee = $user->resolveEmployee();
        if ($employee) {
            $employee->name = $this->name;
            $employee->email = $this->email;
            $employee->save();
        }

        $this->statusMessage = 'Profile updated successfully.';
    }

    public function updatedAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $this->persistAvatar(Auth::user());
        $this->statusMessage = 'Profile photo updated.';
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        $this->avatar = null;
        $this->statusMessage = 'Profile photo removed.';
    }

    protected function persistAvatar($user): void
    {
        if (! $this->avatar) {
            return;
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = $this->avatar->store('users/avatars', 'public');
        $user->save();
        $this->avatar = null;
    }

    public function changePassword(): void
    {
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->password = Hash::make($this->password);
        $user->save();

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->statusMessage = 'Password changed successfully.';
    }

    public function render()
    {
        $user = Auth::user();
        $employee = $user->resolveEmployee();

        return view('livewire.profile.index', [
            'user' => $user,
            'employee' => $employee,
            'roleLabel' => $user->primaryRoleLabel(),
            'workspaceName' => $employee?->workspace?->name,
        ]);
    }
}
