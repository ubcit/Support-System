<?php

namespace Modules\Communication\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Enums\MessageChannel;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Enums\MessageStatus;
use Modules\Customers\Models\Customer;

class Message extends Model
{
    use \Modules\MultiTenancy\Traits\BelongsToWorkspace, HasUuid;

    protected $fillable = [
        'workspace_id',
        'channel',
        'direction',
        'sender_identifier',
        'sender_name',
        'recipient_identifier',
        'subject',
        'body',
        'raw_payload',
        'conversation_id',
        'conversation_session_id',
        'customer_id',
        'status',
        'processing_status',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'channel' => MessageChannel::class,
        'direction' => MessageDirection::class,
        'status' => MessageStatus::class,
        'raw_payload' => 'array',
        'processing_status' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConversationSession::class, 'conversation_session_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
