<?php

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\Contracts\AIProviderInterface;
use Modules\Communication\Models\Conversation;

class AnalyzeMessageThread implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120; // AI can be slow

    public function __construct(
        public Conversation $conversation
    ) {}

    public function handle(AIProviderInterface $aiProvider): void
    {
        Log::info("Starting AI Analysis for Conversation {$this->conversation->uuid}");

        // Load all messages in the conversation chronologically
        $this->conversation->load(['messages.attachments', 'customer']);

        $messages = [];
        $attachments = [];

        foreach ($this->conversation->messages as $message) {
            $messages[] = [
                'role' => $message->direction->value === 'inbound' ? 'user' : 'assistant',
                'text' => $message->body ?? 'No text content',
            ];

            foreach ($message->attachments as $attachment) {
                if ($attachment->processing_status === 'ready') {
                    $attachments[] = [
                        'type' => $attachment->type->value,
                        'reference_id' => $attachment->uuid,
                        // Could include ai_transcript if it was an audio file processed earlier
                    ];
                }
            }
        }

        // Fetch active projects dynamically for this specific customer to feed to the AI context
        $activeProjects = \Modules\Projects\Models\Project::where('status', 'active')
            ->where('customer_id', $this->conversation->customer_id)
            ->pluck('name')->toArray();
            
        $projectHints = empty($activeProjects) 
            ? 'No active projects for this customer currently.' 
            : 'Customer\'s known active projects: ' . implode(', ', $activeProjects);

        // Prepare context
        $context = [
            'project' => $projectHints,
            'customer' => $this->conversation->customer->name ?? 'Unknown Customer',
            'boss_notes' => 'Ensure priority is detected accurately. Mention if billing issue.',
        ];

        try {
            // Call AI Provider with strictly typed method
            $analysisResult = $aiProvider
                ->setPromptVersion(1)
                ->setSchemaVersion(1)
                ->setTemperature(0.2)
                ->analyzeConversation($context, $messages, $attachments);
        } catch (\Exception $e) {
            Log::error("AI Analysis API failed for Conversation {$this->conversation->uuid}: " . $e->getMessage());
            
            // Fallback AI Result so the pipeline doesn't break
            $analysisResult = [
                'title' => 'Manual Triage Needed (AI Failed)',
                'description' => 'The AI provider failed to analyze this thread or ran out of credits. Please review manually.',
                'summary' => 'AI System Failure or Limit Reached.',
                'project' => ['matched' => null, 'confidence' => 0],
                'employee' => ['matched' => null, 'confidence' => 0],
                'tags' => ['ai_failed'],
                'confidence' => 0,
                'schema_version' => 1,
            ];
        }

        try {

            // Save JSON directly to the Conversation metadata (keep history)
            $metadata = $this->conversation->metadata ?? [];
            $aiAnalyses = $metadata['ai_analyses'] ?? [];
            
            // Append with timestamp and provider details
            $aiAnalyses[] = [
                'provider' => 'OpenAI',
                'timestamp' => now()->toIso8601String(),
                'result' => $analysisResult,
            ];
            $metadata['ai_analyses'] = $aiAnalyses;
            
            $this->conversation->update([
                'metadata' => $metadata
            ]);

            // Update processing status for the latest inbound message to prevent re-processing
            $latestInbound = $this->conversation->messages()->where('direction', 'inbound')->latest()->first();
            if ($latestInbound) {
                $status = $latestInbound->processing_status ?? [];
                $status['ai_analyzed'] = true;
                $latestInbound->update(['processing_status' => $status]);
                
                // Trigger Step 4: Resolution Engine
                \Modules\Issues\Jobs\ResolveConversationJob::dispatch($this->conversation, $latestInbound);
            }

            Log::info("AI Analysis completed for Conversation {$this->conversation->uuid}");

        } catch (\Exception $e) {
            Log::error("AI Analysis failed for Conversation {$this->conversation->uuid}: " . $e->getMessage());
            
            $latestInbound = $this->conversation->messages()->where('direction', 'inbound')->latest()->first();
            if ($latestInbound) {
                $status = $latestInbound->processing_status ?? [];
                $status['ai_analyzed_failed'] = true;
                $latestInbound->update(['processing_status' => $status]);
            }
            
            throw $e;
        }
    }
}
