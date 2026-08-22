<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\SyncProviderInterface;
use App\Helpers\AppShell;
use App\Policies\AttachmentPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\IssuePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Services\Mock\MockAIProvider;
use App\Services\Mock\MockSyncProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAssignment;

use function Livewire\before;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function () {
            if ($this->app->environment('local', 'testing')) {
                return new MockAIProvider();
            }
            return new \App\Services\AI\Providers\OpenAIProvider();
        });

        $this->app->bind(
            SyncProviderInterface::class,
            MockSyncProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // General API rate limit, applied to the whole `api` middleware group
        // via `->throttleApi()` in bootstrap/app.php. Previously there was no
        // named "api" limiter at all, so calling throttleApi() without this
        // would 429 every single request (non-numeric limiter name).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Stricter limit specifically for the public, unauthenticated
        // auth endpoints (`/api/v1/auth/login`, `/register`), which were
        // previously brute-forceable/spammable with no throttling at all.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Same underlying reason as the Gate::policy() calls below: models
        // live under Modules\*\Models\{X} rather than App\Models\{X}, so
        // Factory's default resolver (which special-cases the App\Models\
        // namespace and otherwise falls through unresolved) can't find
        // Database\Factories\{X}Factory for them. Resolve by basename only,
        // regardless of the model's actual namespace.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Models live under Modules\*\Models, so Laravel's default
        // App\Models\{X} -> App\Policies\{X}Policy guesser can't find these;
        // register them explicitly instead.
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Issue::class, IssuePolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);

        $this->registerFlashToastBridge();
        $this->registerAppShellRefresh();
    }

    /**
     * The secondary sidebar (inbox / tasks / projects) and rail badges live
     * outside the page Livewire component, so they stayed stale after a
     * delete until a full refresh. Any sidebar-visible model write now
     * dispatches `app-shell-updated` so those panels re-query in place.
     */
    protected function registerAppShellRefresh(): void
    {
        $models = [
            TaskAssignment::class,
            Conversation::class,
            ConversationSession::class,
            Message::class,
            Project::class,
        ];

        foreach ($models as $model) {
            $model::saved(fn () => AppShell::refresh());
            $model::deleted(fn () => AppShell::refresh());
        }

        Task::saved(function (Task $task) {
            if ($task->wasChanged('completed_at') || $task->wasChanged('current_state_id')) {
                ConversationSession::syncDoneForTask($task);
            }
            AppShell::refresh();
        });
        Task::deleted(function () {
            AppShell::refresh();
        });
    }

    /**
     * The app has ~60 call sites across ~29 Livewire components that call
     * `session()->flash('success'|'error'|'warning'|'info', ...)`, but only
     * a handful of page templates actually render that flash data — most
     * user actions silently showed no feedback at all. Rather than touching
     * every call site (or every template), hook into Livewire's `dehydrate`
     * lifecycle (fired after every component render, on both full page loads
     * and same-page `wire:click` AJAX updates) and turn any pending flash
     * into a `toast` browser event. A single listener in the app-shell layout
     * (`layouts/app-shell/toast.blade.php`) renders it as a real, animated,
     * auto-dismissing toast that survives `wire:navigate`.
     *
     * `session()->pull()` both reads and clears the key, so if multiple
     * components dehydrate within the same request only the first one
     * dispatches the toast.
     *
     * Must use the `before('dehydrate', ...)` hook (not `Livewire::listen()`,
     * which registers a normal/after listener) — Livewire's own
     * `SupportEvents::dehydrate()` hook is what reads queued `dispatch()`
     * calls into the response's `dispatches` effect, and it's registered
     * as a normal listener during `LivewireServiceProvider::boot()`, which
     * runs before this provider's `boot()`. A normal listener registered
     * here would run *after* that snapshot is already taken, so anything
     * dispatched here would silently never reach the browser.
     */
    protected function registerFlashToastBridge(): void
    {
        before('dehydrate', function ($component) {
            foreach (['success', 'error', 'warning', 'info'] as $type) {
                if (session()->has($type)) {
                    $component->dispatch('toast', type: $type, message: session()->pull($type));
                }
            }
        });
    }
}
