<?php

use App\Http\Controllers\ScheduleController;
use App\Livewire\Ai\Models\Create as AiModelsCreate;
use App\Livewire\Ai\Models\Edit as AiModelsEdit;
use App\Livewire\Ai\Models\Index as AiModelsIndex;
use App\Livewire\Ai\Models\View as AiModelsView;
use App\Livewire\Ai\Prompts\Create as AiPromptsCreate;
use App\Livewire\Ai\Prompts\Edit as AiPromptsEdit;
use App\Livewire\Ai\Prompts\Index as AiPromptsIndex;
use App\Livewire\Ai\Prompts\View as AiPromptsView;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\BossWorkspace\Index;
use App\Livewire\CertificationTests\Create;
use App\Livewire\CertificationTests\Edit;
use App\Livewire\CertificationTests\View;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\FileManager\Drive;
use App\Livewire\Profile\Index as ProfileIndex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Attachments\Controllers\AttachmentController;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->route(Auth::user()->preferredHomeRoute());
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', DashboardIndex::class)->name('dashboard');
    Route::get('/profile', ProfileIndex::class)->name('profile');
    Route::get('/boss-workspace', Index::class)->name('boss-workspace');
    Route::get('/operations-dashboard', App\Livewire\OperationsDashboard\Index::class)->name('operations-dashboard');

    Route::get('/ai-center', App\Livewire\AiCenter\Index::class)->name('ai-center');
    Route::get('/task-dashboard', App\Livewire\TaskDashboard\Index::class)->name('task-dashboard');
    Route::get('/task-detail/{record?}', App\Livewire\TaskDetail\Index::class)->name('task-detail');
    Route::redirect('/schedule', '/admin/task-dashboard?view=calendar')->name('schedule');
    Route::get('/schedule/events', [ScheduleController::class, 'events'])->name('schedule.events');
    Route::patch('/schedule/tasks/{uuid}/reschedule', [ScheduleController::class, 'reschedule'])->name('schedule.reschedule');
    Route::get('/project-hub', App\Livewire\ProjectHub\Index::class)->name('project-hub');
    Route::get('/file-manager', App\Livewire\FileManager\Index::class)->name('file-manager');
    Route::get('/file-manager/drive', Drive::class)->name('file-manager.drive');
    Route::get('/customer-crm', App\Livewire\CustomerCRM\Index::class)->name('customer-crm');
    Route::get('/employee-management', App\Livewire\EmployeeManagement\Index::class)->name('employee-management');
    Route::get('/signup-requests', App\Livewire\SignupRequests\Index::class)->name('signup-requests');
    Route::get('/reports-hub', App\Livewire\ReportsHub\Index::class)->name('reports-hub');
    Route::get('/customer-ai-limits', App\Livewire\CustomerAiLimits\Index::class)->name('customer-ai-limits');
    Route::get('/global-timeline', App\Livewire\GlobalTimeline\Index::class)->name('global-timeline');
    Route::redirect('/kanban-board', '/admin/task-dashboard?view=board')->name('kanban-board');
    Route::get('/conversation-center', App\Livewire\ConversationCenter\Index::class)->name('conversation-center');
    Route::redirect('/conversation-explorer', '/admin/conversation-center')->name('conversation-explorer');
    Route::get('/issues', App\Livewire\Issues\Index::class)->name('issues');
    Route::get('/rules-center', App\Livewire\RulesCenter\Index::class)->name('rules-center');
    Route::get('/prompt-playground', App\Livewire\PromptPlayground\Index::class)->name('prompt-playground');
    Route::get('/message-simulator', App\Livewire\MessageSimulator\Index::class)->name('message-simulator');
    Route::get('/laravel-logs', App\Livewire\LaravelLogs\Index::class)->name('laravel-logs');
    Route::get('/worker-logs', App\Livewire\WorkerLogs\Index::class)->name('worker-logs');
    Route::get('/benchmark-dashboard', App\Livewire\BenchmarkDashboard\Index::class)->name('benchmark-dashboard');
    Route::get('/workspace-onboarding', App\Livewire\WorkspaceOnboarding\Index::class)->name('workspace-onboarding');
    Route::get('/workspace-settings', App\Livewire\WorkspaceSettings\Index::class)->name('workspace-settings');

    Route::prefix('ai/models')->group(function () {
        Route::get('/', AiModelsIndex::class)->name('ai.models.index');
        Route::get('/create', AiModelsCreate::class)->name('ai.models.create');
        Route::get('/{model}', AiModelsView::class)->name('ai.models.view');
        Route::get('/{model}/edit', AiModelsEdit::class)->name('ai.models.edit');
    });

    Route::prefix('ai/prompts')->group(function () {
        Route::get('/', AiPromptsIndex::class)->name('ai.prompts.index');
        Route::get('/create', AiPromptsCreate::class)->name('ai.prompts.create');
        Route::get('/{prompt}', AiPromptsView::class)->name('ai.prompts.view');
        Route::get('/{prompt}/edit', AiPromptsEdit::class)->name('ai.prompts.edit');
    });

    Route::prefix('ai/schemas')->group(function () {
        Route::get('/', App\Livewire\Ai\Schemas\Index::class)->name('ai.schemas.index');
        Route::get('/create', App\Livewire\Ai\Schemas\Create::class)->name('ai.schemas.create');
        Route::get('/{schema}', App\Livewire\Ai\Schemas\View::class)->name('ai.schemas.view');
        Route::get('/{schema}/edit', App\Livewire\Ai\Schemas\Edit::class)->name('ai.schemas.edit');
    });

    Route::prefix('ai/request-logs')->group(function () {
        Route::get('/', App\Livewire\Ai\RequestLogs\Index::class)->name('ai.request-logs.index');
        Route::get('/{log}', App\Livewire\Ai\RequestLogs\View::class)->name('ai.request-logs.view');
    });

    Route::prefix('certification-tests')->group(function () {
        Route::get('/', App\Livewire\CertificationTests\Index::class)->name('certification-tests.index');
        Route::get('/create', Create::class)->name('certification-tests.create');
        Route::get('/{test}', View::class)->name('certification-tests.view');
        Route::get('/{test}/edit', Edit::class)->name('certification-tests.edit');
    });

    Route::prefix('pipeline-logs')->group(function () {
        Route::get('/', App\Livewire\PipelineLogs\Index::class)->name('pipeline-logs.index');
        Route::get('/{log}', App\Livewire\PipelineLogs\View::class)->name('pipeline-logs.view');
    });
});

// Attachment preview/download — auth only; AttachmentPolicy gates access.
// Kept outside /admin so employees can open files from workspace task-detail.
Route::middleware('auth')->prefix('attachments')->group(function () {
    Route::get('/{uuid}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::get('/{uuid}/media', [AttachmentController::class, 'media'])->name('attachments.media');
});

Route::middleware(['auth', 'workspace'])->prefix('workspace')->name('workspace.')->group(function () {
    Route::get('/', App\Livewire\Workspace\Dashboard\Index::class)->name('dashboard');
    Route::get('/my-tasks', App\Livewire\TaskDashboard\Index::class)->name('employee');
    Route::get('/tasks/{record}', App\Livewire\TaskDetail\Index::class)->name('task-detail');
    Route::get('/profile', ProfileIndex::class)->name('profile');
});
