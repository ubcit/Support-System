<?php

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\Contracts\AIProviderInterface;
use Modules\AI\DTOs\AIResult;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;

class ProcessBossCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public array $messageData,
        public string $fromPhone,
        public Employee $boss
    ) {}

    public function handle(AIProviderInterface $aiProvider): void
    {
        Log::info("ProcessBossCommand started for Boss {$this->boss->name}");

        $body = $this->messageData['text']['body'] ?? '';

        if (empty($body)) {
            Log::warning("Boss Command ignored due to empty text body.");
            return;
        }

        $activeProjects = Project::where('status', 'active')->pluck('name')->toArray();
        $projectHints = empty($activeProjects) 
            ? 'No active projects currently.' 
            : 'Known active projects: ' . implode(', ', $activeProjects);

        $context = [
            'project' => $projectHints,
            'customer' => 'Internal Boss Command',
            'boss_notes' => 'This is a direct command from the boss. Extract the requested project and task precisely.',
        ];

        $messages = [
            ['role' => 'user', 'text' => $body]
        ];

        try {
            $analysisResult = $aiProvider
                ->setPromptVersion(1)
                ->setSchemaVersion(1)
                ->setTemperature(0.1)
                ->analyzeConversation($context, $messages, []);

            $aiResult = AIResult::fromArray($analysisResult);
            
            // Match Project
            $project = null;
            if ($aiResult->projectMatch) {
                $project = Project::where('name', 'LIKE', "%{$aiResult->projectMatch}%")->first();
            }

            // Create an Issue directly linked to the Boss command
            $workflowManager = app(\Modules\Workflows\Services\WorkflowManager::class);
            $initialState = $workflowManager->getDefaultState('issue');

            $issue = Issue::create([
                'title' => $aiResult->title ?? 'Boss Direct Task',
                'description' => $body, // Keep original body as description
                'project_id' => $project?->id,
                'workflow_id' => $initialState?->workflow_id,
                'current_state_id' => $initialState?->id,
                'ai_summary' => "Boss Command: " . $aiResult->summary,
                'metadata' => [
                    'source' => 'boss_command',
                    'boss_id' => $this->boss->id,
                ]
            ]);

            // Now automatically create the work items via the standard engine
            \Modules\Tasks\Jobs\RunWorkManagementEngine::dispatch($issue, $aiResult);

            Log::info("Boss command processed successfully. Issue {$issue->uuid} generated.");

        } catch (\Exception $e) {
            Log::error("ProcessBossCommand AI parsing failed: " . $e->getMessage());

            // Fallback: Create unassigned issue with original text
            $workflowManager = app(\Modules\Workflows\Services\WorkflowManager::class);
            $initialState = $workflowManager->getDefaultState('issue');

            $issue = Issue::create([
                'title' => 'Boss Direct Task (Unparsed)',
                'description' => $body,
                'workflow_id' => $initialState?->workflow_id,
                'current_state_id' => $initialState?->id,
                'ai_summary' => 'AI parsing failed. Please assign project manually.',
                'metadata' => [
                    'source' => 'boss_command',
                    'boss_id' => $this->boss->id,
                    'needs_review' => true,
                ]
            ]);
        }
    }
}
