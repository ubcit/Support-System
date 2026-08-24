<?php

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Controllers\AuthController;
use Modules\Customers\Controllers\CustomerController;
use Modules\Projects\Controllers\ProjectController;
use Modules\Issues\Controllers\IssueController;
use Modules\Tasks\Controllers\NativeTaskController;
use Modules\Tasks\Controllers\KanbanController;
use Modules\Tasks\Controllers\TimelineController;
use Modules\Tasks\Controllers\CalendarController;
use Modules\Tasks\Controllers\EmployeeWorkspaceController;
use Modules\Tasks\Controllers\BossWorkspaceController;
use Modules\Tasks\Controllers\GlobalSearchController;
use Modules\Notifications\Controllers\NotificationCenterController;
use Modules\AI\Controllers\AICopilotController;
use Modules\Employees\Controllers\EmployeeController;
use Modules\Attachments\Controllers\AttachmentController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1
| Authentication routes are public, all others require Sanctum auth.
|
*/

Route::prefix('v1')->group(function () {

    // ─── Authentication ──────────────────────────────────────────
    // Public self-registration is intentionally disabled: accounts are
    // provisioned by an admin via the Employee Management UI (which creates
    // the linked User + Employee + workspace assignment together), not via
    // an open API endpoint. AuthController::register()/RegisterRequest are
    // kept but are unrouted.
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('/login', [AuthController::class, 'login']);
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // ─── Webhooks (Public) ──────────────────────────────────────
    Route::prefix('webhooks')->group(function () {
        Route::match(['get', 'post'], '/whatsapp', [\Modules\Communication\Controllers\WebhookController::class, 'handleWhatsApp']);
        Route::post('/email', [\Modules\Communication\Controllers\EmailWebhookController::class, 'handle']);
        Route::post('/sync/{provider}', [\Modules\Synchronization\Controllers\ProviderWebhookController::class, 'handle']);
    });

    // ─── Protected Routes ────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // ─── Native Work Platform - Tasks ───────────────────────
        Route::get('/tasks/kanban', [KanbanController::class, 'board']);
        Route::patch('/tasks/kanban/{uuid}/move', [KanbanController::class, 'moveCard']);
        Route::get('/tasks/timeline', [TimelineController::class, 'timeline']);
        Route::get('/tasks/calendar', [CalendarController::class, 'events']);
        Route::patch('/tasks/calendar/{uuid}/reschedule', [CalendarController::class, 'reschedule']);
        Route::post('/tasks/bulk', [NativeTaskController::class, 'bulkUpdate']);

        Route::apiResource('tasks', NativeTaskController::class)->parameters(['tasks' => 'uuid']);

        Route::prefix('tasks/{uuid}')->group(function () {
            Route::post('/restore', [NativeTaskController::class, 'restore']);
            Route::post('/subtasks', [NativeTaskController::class, 'createSubtask']);
            Route::post('/timer/start', [NativeTaskController::class, 'startTimer']);
            Route::post('/timer/stop', [NativeTaskController::class, 'stopTimer']);
        });

        Route::patch('/checklist-items/{itemUuid}/toggle', [NativeTaskController::class, 'toggleChecklist']);

        // ─── Workspaces ──────────────────────────────────────────
        Route::get('/employee-workspace', [EmployeeWorkspaceController::class, 'workspace']);
        Route::post('/employee-workspace/tasks/{uuid}/bookmark', [EmployeeWorkspaceController::class, 'bookmarkTask']);

        Route::get('/boss-workspace', [BossWorkspaceController::class, 'overview']);

        // ─── Global Search ───────────────────────────────────────
        Route::get('/search', [GlobalSearchController::class, 'search']);

        // ─── Notifications Center ──────────────────────────────
        Route::get('/notifications', [NotificationCenterController::class, 'index']);
        Route::patch('/notifications/{uuid}/read', [NotificationCenterController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllRead']);

        // ─── AI Copilot ──────────────────────────────────────────
        Route::prefix('ai/copilot')->group(function () {
            Route::get('/tasks/{uuid}/summary', [AICopilotController::class, 'summarizeTask']);
            Route::get('/conversations/{id}/summary', [AICopilotController::class, 'summarizeConversation']);
            Route::get('/projects/{uuid}/summary', [AICopilotController::class, 'summarizeProject']);
            Route::post('/checklists/generate', [AICopilotController::class, 'generateChecklist']);
            Route::get('/tasks/{uuid}/reply', [AICopilotController::class, 'generateReply']);
        });

        // ─── Customers ──────────────────────────────────────────
        Route::apiResource('customers', CustomerController::class)
            ->parameters(['customers' => 'uuid']);

        // ─── Projects ───────────────────────────────────────────
        Route::apiResource('projects', ProjectController::class)
            ->parameters(['projects' => 'uuid']);

        // ─── Issues ─────────────────────────────────────────────
        Route::apiResource('issues', IssueController::class)
            ->parameters(['issues' => 'uuid']);

        Route::prefix('issues/{uuid}')->group(function () {
            Route::patch('/status', [IssueController::class, 'changeStatus']);
            Route::patch('/assign', [IssueController::class, 'assign']);
            Route::get('/comments', [IssueController::class, 'comments']);
            Route::post('/comments', [IssueController::class, 'addComment']);
            Route::get('/timeline', [IssueController::class, 'timeline']);
        });

        // ─── Employees ──────────────────────────────────────────
        Route::apiResource('employees', EmployeeController::class)
            ->parameters(['employees' => 'uuid']);

        Route::prefix('employees/{uuid}')->group(function () {
            Route::get('/tasks', [EmployeeController::class, 'tasks']);
            Route::get('/issues', [EmployeeController::class, 'issues']);
            Route::get('/skills', [EmployeeController::class, 'skills']);
        });

        // ─── Attachments ────────────────────────────────────────
        Route::post('/attachments', [AttachmentController::class, 'store']);
        Route::get('/attachments/{uuid}', [AttachmentController::class, 'show']);
        Route::delete('/attachments/{uuid}', [AttachmentController::class, 'destroy']);
        Route::get('/attachments/{uuid}/download', [AttachmentController::class, 'download']);
    });
});
