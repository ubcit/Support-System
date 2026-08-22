<?php

namespace App\Livewire\Header;

use Livewire\Component;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Services\NotificationService;

class Notifications extends Component
{
    public function markAsRead(string $uuid): void
    {
        $notification = Notification::where('uuid', $uuid)->first();

        if (! $notification) {
            return;
        }

        $user = auth()->user();
        $employee = $user?->resolveEmployee();

        if ($employee && $notification->employee_id !== $employee->id) {
            return;
        }

        if (! $employee && $notification->user_id !== $user?->id) {
            return;
        }

        app(NotificationService::class)->markAsRead($notification);
    }

    public function markAllAsRead(): void
    {
        $user = auth()->user();
        $employee = $user?->resolveEmployee();

        if ($employee) {
            app(NotificationService::class)->markAllAsRead($employee);
        } elseif ($user) {
            Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }

    public function render()
    {
        $user = auth()->user();
        $employee = $user?->resolveEmployee();

        $notifications = collect();
        $unreadCount = 0;

        if ($employee) {
            $query = Notification::where('employee_id', $employee->id);
        } elseif ($user) {
            $query = Notification::where('user_id', $user->id);
        } else {
            return view('livewire.header.notifications', [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
            ]);
        }

        $notifications = (clone $query)->orderBy('created_at', 'desc')->limit(15)->get();
        $unreadCount = (clone $query)->whereNull('read_at')->count();

        return view('livewire.header.notifications', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
