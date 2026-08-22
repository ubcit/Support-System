<?php

namespace Modules\Notifications\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Employees\Models\Employee;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Services\NotificationService;

class NotificationCenterController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->actor();

        if (! $employee) {
            return response()->json(['data' => []]);
        }

        $notifications = Notification::where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 20));

        return response()->json($notifications);
    }

    public function markAsRead(string $uuid): JsonResponse
    {
        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }

        $notification = Notification::where('uuid', $uuid)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $this->notificationService->markAsRead($notification);

        return response()->json(['data' => $notification]);
    }

    public function markAllRead(): JsonResponse
    {
        $employee = $this->actor();

        if ($employee) {
            $this->notificationService->markAllAsRead($employee);
        }

        return response()->json(['message' => 'All notifications marked as read']);
    }

    protected function actor(): ?Employee
    {
        return auth()->user()?->resolveEmployee();
    }
}
