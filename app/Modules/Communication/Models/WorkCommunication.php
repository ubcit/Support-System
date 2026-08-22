<?php

namespace Modules\Communication\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCommunication extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'work_communications';

    protected $fillable = [
        'communicable_type',
        'communicable_id',
        'parent_id',
        'author_type',
        'author_id',
        'type',
        'visibility',
        'status',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function communicable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function attachments()
    {
        return $this->morphMany(\Modules\Attachments\Models\Attachment::class, 'attachable');
    }
}
