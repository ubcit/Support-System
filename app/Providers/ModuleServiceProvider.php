<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Customer
use Modules\Customers\Repositories\CustomerRepositoryInterface;
use Modules\Customers\Repositories\CustomerRepository;

// Project
use Modules\Projects\Repositories\ProjectRepositoryInterface;
use Modules\Projects\Repositories\ProjectRepository;

// Issue
use Modules\Issues\Repositories\IssueRepositoryInterface;
use Modules\Issues\Repositories\IssueRepository;

// Employee
use Modules\Employees\Repositories\EmployeeRepositoryInterface;
use Modules\Employees\Repositories\EmployeeRepository;

// Attachment
use Modules\Attachments\Repositories\AttachmentRepositoryInterface;
use Modules\Attachments\Repositories\AttachmentRepository;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repositories
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(IssueRepositoryInterface::class, IssueRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->bind(AttachmentRepositoryInterface::class, AttachmentRepository::class);
        $this->app->bind(\Modules\Communication\Repositories\ConversationRepositoryInterface::class, \Modules\Communication\Repositories\ConversationRepository::class);
        $this->app->bind(\Modules\AI\Contracts\AIProviderInterface::class, \Modules\AI\Providers\OpenAIProvider::class);

        // Synchronization Manager as Singleton
        $this->app->singleton(\Modules\Synchronization\Services\SynchronizationManager::class, function ($app) {
            $manager = new \Modules\Synchronization\Services\SynchronizationManager();
            $manager->registerProvider(new \Modules\Synchronization\Providers\GenericSyncProvider());
            return $manager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Domain Events
        \Illuminate\Support\Facades\Event::listen(
            \Modules\Tasks\Events\TaskCreated::class,
            \Modules\Tasks\Listeners\SyncTaskToExternalProvider::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \Modules\Workflows\Events\TaskStateChanged::class,
            \Modules\Statistics\Listeners\AggregateTaskMetrics::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \Modules\Workflows\Events\IssueStateChanged::class,
            \Modules\Statistics\Listeners\AggregateIssueMetrics::class
        );

        // --- Rules Engine Globals ---
        $rulesListener = \Modules\Rules\Listeners\EvaluateRules::class;
        \Illuminate\Support\Facades\Event::listen(\Modules\Workflows\Events\TaskStateChanged::class, $rulesListener);
        \Illuminate\Support\Facades\Event::listen(\Modules\Workflows\Events\IssueStateChanged::class, $rulesListener);
        \Illuminate\Support\Facades\Event::listen(\Modules\Communication\Events\CommunicationCreated::class, $rulesListener);
        \Illuminate\Support\Facades\Event::listen(\Modules\Tasks\Events\TaskCreated::class, $rulesListener);
        \Illuminate\Support\Facades\Event::listen(\Modules\Health\Events\SystemHealthDegraded::class, $rulesListener);
        
        // --- Health Engine Setup ---
        $this->app->singleton(\Modules\Health\Services\HealthEngine::class, function ($app) {
            $engine = new \Modules\Health\Services\HealthEngine();
            $engine->registerCheck(new \Modules\Health\Checks\DatabaseHealthCheck());
            $engine->registerCheck(new \Modules\Health\Checks\QueueHealthCheck());
            return $engine;
        });

        // --- Telemetry Platform Setup ---
        $this->app->singleton(\Modules\Telemetry\Services\MetricsRegistry::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Health\Console\CheckPlatformHealth::class,
            ]);
        }
    }
}
