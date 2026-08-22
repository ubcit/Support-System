<?php

namespace Modules\Issues\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\Message;
use Modules\Issues\Services\ConversationResolutionEngine;

class ResolveConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public Conversation $conversation,
        public Message $latestMessage
    ) {}

    public function handle(ConversationResolutionEngine $engine): void
    {
        Log::info("ResolveConversationJob started for Conversation {$this->conversation->uuid}");

        try {
            $engine->resolve($this->conversation, $this->latestMessage);
        } catch (\Exception $e) {
            Log::error("Resolution Engine failed for Conversation {$this->conversation->uuid}: " . $e->getMessage());
            
            $status = $this->latestMessage->processing_status ?? [];
            $status['issue_linked_failed'] = true;
            $this->latestMessage->update(['processing_status' => $status]);
            
            throw $e;
        }
    }
}
