<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiSchema;
use Illuminate\Database\Seeder;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Models\WorkflowTransition;

class EssentialPlatformSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default workspace — Gemini is the release AI provider
        Workspace::updateOrCreate(
            ['slug' => 'space-workspace'],
            [
                'name' => 'Space Workspace',
                'is_active' => true,
                'settings' => [
                    'ai_provider' => 'gemini',
                    'whatsapp_phone' => '+9647700000000',
                    'customer_cooldown_minutes' => 5,
                    'boss_cooldown_minutes' => 1,
                    'auto_create_tasks' => true,
                    'auto_reply_on_completion' => true,
                    'auto_reply_on_processing' => true,
                    'staff_notify_on_session' => true,
                    'auto_create_min_confidence' => 0.70,
                ],
            ]
        );

        // 2. Default workflow & states
        $workflow = Workflow::firstOrCreate(
            ['name' => 'Space Workflow'],
            [
                'entity_type' => 'task',
                'is_default' => true,
            ]
        );

        $todo = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'To Do'],
            ['type' => 'initial', 'order' => 1]
        );

        $inProgress = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'In Progress'],
            ['type' => 'active', 'order' => 2]
        );

        $review = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'Review'],
            ['type' => 'active', 'order' => 3]
        );

        $done = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'Done'],
            ['type' => 'completed', 'order' => 4]
        );

        WorkflowTransition::firstOrCreate([
            'workflow_id' => $workflow->id,
            'from_state_id' => $todo->id,
            'to_state_id' => $inProgress->id,
        ]);

        WorkflowTransition::firstOrCreate([
            'workflow_id' => $workflow->id,
            'from_state_id' => $inProgress->id,
            'to_state_id' => $review->id,
        ]);

        WorkflowTransition::firstOrCreate([
            'workflow_id' => $workflow->id,
            'from_state_id' => $review->id,
            'to_state_id' => $done->id,
        ]);

        // 3. Gemini model (primary for release)
        $geminiModelId = config('services.gemini.model', 'gemini-3.5-flash-lite');

        AiModel::updateOrCreate(
            ['name' => 'Gemini Flash'],
            [
                'provider' => 'gemini',
                'api_model_id' => $geminiModelId,
                'context_length' => 1000000,
                'supports_vision' => true,
                'supports_audio' => false,
                'supports_json' => true,
                'supports_tools' => false,
                'is_active' => true,
            ]
        );

        // Keep OpenAI model available but inactive by default
        AiModel::updateOrCreate(
            ['name' => 'GPT-4o'],
            [
                'provider' => 'openai',
                'api_model_id' => config('services.openai.model', 'gpt-4o'),
                'context_length' => 128000,
                'supports_vision' => true,
                'supports_audio' => false,
                'supports_json' => true,
                'supports_tools' => true,
                'is_active' => false,
            ]
        );

        // Deterministic mock model for Message Simulator / certification (local + tests)
        AiModel::updateOrCreate(
            ['name' => 'Mock Extractor'],
            [
                'provider' => 'mock',
                'api_model_id' => 'mock-v1',
                'context_length' => 32000,
                'supports_vision' => false,
                'supports_audio' => false,
                'supports_json' => true,
                'supports_tools' => false,
                'is_active' => true,
            ]
        );

        // 4. Default AI schema
        $schema = AiSchema::firstOrCreate(
            ['name' => 'Multi-Task Extraction Schema (v2)'],
            [
                'version' => 2,
                'schema_json' => [
                    'type' => 'object',
                    'properties' => [
                        'is_actionable' => ['type' => 'boolean'],
                        'intent' => ['type' => 'string'],
                        'tasks' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'priority' => ['type' => 'string'],
                                    'assigned_to' => ['type' => 'string'],
                                ],
                                'required' => ['title', 'description', 'priority'],
                            ],
                        ],
                        'project' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                    ],
                    'required' => ['is_actionable', 'intent', 'tasks', 'summary', 'confidence'],
                ],
            ]
        );

        // 5. Default AI prompt
        AiPrompt::firstOrCreate(
            ['name' => 'Multi-Task Extraction Prompt (v2)'],
            [
                'version' => 2,
                'system_prompt' => <<<'PROMPT'
You are an information extraction engine for a project management system. Your job is to analyze customer or boss messages and extract structured task information.

RULES:
1. If the message is a greeting, casual chat, or contains no actionable work request, set is_actionable to false, intent to "greeting" or "casual", and return an empty tasks array.
2. If the message contains one or more actionable requests (bug reports, feature requests, tasks, complaints), set is_actionable to true.
3. Extract EACH distinct task/issue mentioned into a separate object in the tasks array.
4. For priority, infer from urgency cues: "urgent"/"asap"/"crashes"/"down" = "urgent", "important"/"soon" = "high", normal requests = "medium", "when you can"/"low priority" = "low".
5. For assigned_to, if the boss mentions an employee name (e.g. "assign to Ahmed", "give this to Sara"), extract that name. Otherwise leave as empty string.
6. For project, try to match the mentioned project name. If none mentioned, leave as empty string.
7. Correct grammar in descriptions without changing intent.
8. Provide a confidence score (0.0 to 1.0) for the overall extraction quality.
9. For user-facing text fields (summary, title, and each task title/description), include both English and Arabic in the same string using:
   English: ...
   Arabic: ...
PROMPT,
                'user_prompt_template' => "Context:\n- Boss Notes: {{boss_notes}}\n- Known Projects: {{projects}}\n- Known Employees: {{employees}}\n\nCustomer/Boss Messages:\n{{message}}",
                'temperature' => 0.2,
                'ai_schema_id' => $schema->id,
                'is_active' => true,
            ]
        );
    }
}
