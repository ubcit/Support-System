<?php

namespace Modules\Communication\Models;

use App\Shared\Traits\Filterable;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Communication\Enums\MessageDirection;
use Modules\Customers\Models\Customer;
use Modules\Issues\Models\Issue;

class Conversation extends Model
{
    use \Modules\MultiTenancy\Traits\BelongsToWorkspace, Filterable, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'customer_id',
        'channel',
        'status',
        'last_message_at',
        'last_read_at',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_read_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected array $filterable = [
        'channel',
        'status',
        'customer_id',
    ];

    protected array $sortable = [
        'last_message_at',
        'created_at',
        'updated_at',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ConversationSession::class);
    }

    public function latestSession(): HasOne
    {
        return $this->hasOne(ConversationSession::class)->latestOfMany();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Open threads waiting on a reply: latest message is inbound and newer than last_read_at.
     */
    public function scopeUnread($query)
    {
        $latestDirection = '(SELECT direction FROM messages WHERE messages.conversation_id = conversations.id ORDER BY created_at DESC LIMIT 1)';
        $latestCreated = '(SELECT created_at FROM messages WHERE messages.conversation_id = conversations.id ORDER BY created_at DESC LIMIT 1)';

        return $query->where('status', '!=', 'closed')
            ->whereRaw($latestDirection.' = ?', ['inbound'])
            ->where(function ($q) use ($latestCreated) {
                $q->whereNull('last_read_at')
                    ->orWhereRaw($latestCreated.' > conversations.last_read_at');
            });
    }

    public function isUnread(): bool
    {
        if ($this->status === 'closed') {
            return false;
        }

        $latest = $this->relationLoaded('latestMessage')
            ? $this->latestMessage
            : $this->latestMessage()->first();

        if (! $latest) {
            return false;
        }

        $direction = $latest->direction instanceof MessageDirection
            ? $latest->direction
            : MessageDirection::tryFrom((string) $latest->direction);

        if ($direction !== MessageDirection::Inbound) {
            return false;
        }

        return $this->last_read_at === null || $latest->created_at->gt($this->last_read_at);
    }

    public function scopeTab($query, string $tab)
    {
        return match ($tab) {
            'closed' => $query->where('status', 'closed'),
            'unread' => $query->unread(),
            default => $query,
        };
    }

    public function scopeSearchCustomers($query, string $search)
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        return $query->whereHas('customer', function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%');
        });
    }
}
