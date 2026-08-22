<?php

namespace Modules\Attachments\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Employees\Models\Employee;

class Attachment extends Model
{
    use \Modules\MultiTenancy\Traits\BelongsToWorkspace, HasUuid, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'attachable_type',
        'attachable_id',
        'original_name',
        'stored_path',
        'disk',
        'mime_type',
        'size_bytes',
        'type',
        'sha256',
        'provider',
        'provider_media_id',
        'provider_url',
        'downloaded_at',
        'processing_status',
        'ai_transcript',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'type' => AttachmentType::class,
        'size_bytes' => 'integer',
        'downloaded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileNameAttribute(): string
    {
        return $this->original_name ?? 'Attachment';
    }

    public function getFilePathAttribute(): string
    {
        return $this->stored_path ?? '';
    }

    public function getFileSizeAttribute(): int
    {
        return $this->size_bytes ?? 0;
    }
}
