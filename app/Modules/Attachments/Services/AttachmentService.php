<?php

namespace Modules\Attachments\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Attachments\Models\Attachment;
use Modules\Attachments\Repositories\AttachmentRepositoryInterface;
use Modules\Customers\Models\Customer;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class AttachmentService
{
    /**
     * Explicit allow-list of model classes files may be attached to. Shared
     * by Modules\Attachments\Controllers\AttachmentController (the API) and
     * any UI upload path (e.g. App\Livewire\TaskDetail), so both entry
     * points enforce the same rule instead of drifting apart.
     *
     * @var list<class-string>
     */
    public const ALLOWED_ATTACHABLE_TYPES = [
        Task::class,
        Project::class,
        Issue::class,
        Customer::class,
    ];

    /**
     * Extensions considered safe to store, mirroring the categories already
     * recognized by AttachmentType::fromMimeType() (images, audio, video,
     * PDFs, common office documents/spreadsheets, and plain archives).
     * Anything else (executables, scripts, etc.) is rejected.
     */
    public const ALLOWED_EXTENSIONS = 'jpg,jpeg,png,gif,webp,heic,bmp,'
        .'mp3,wav,m4a,ogg,oga,'
        .'mp4,mov,avi,webm,'
        .'pdf,doc,docx,txt,rtf,'
        .'xls,xlsx,csv,'
        .'zip,rar,7z,gz';

    public function __construct(
        protected AttachmentRepositoryInterface $repository
    ) {}

    /**
     * Store an uploaded file and create an attachment record.
     */
    public function store(
        UploadedFile $file,
        string $attachableType,
        int $attachableId,
        ?int $uploadedBy = null,
        string $disk = 'local'
    ): Attachment {
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $type = AttachmentType::fromMimeType($mimeType);

        // Store file in a structured directory
        $directory = 'attachments/' . strtolower(class_basename($attachableType)) . '/' . $attachableId;
        $path = $file->store($directory, $disk);

        return $this->repository->create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'original_name' => $originalName,
            'stored_path' => $path,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'type' => $type->value,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Store a file from a URL (e.g., WhatsApp media download).
     */
    public function storeFromUrl(
        string $url,
        string $filename,
        string $mimeType,
        string $attachableType,
        int $attachableId,
        ?int $uploadedBy = null,
        string $disk = 'local'
    ): Attachment {
        $type = AttachmentType::fromMimeType($mimeType);
        $directory = 'attachments/' . strtolower(class_basename($attachableType)) . '/' . $attachableId;
        $path = $directory . '/' . $filename;

        // Download and store
        $contents = file_get_contents($url);
        Storage::disk($disk)->put($path, $contents);

        return $this->repository->create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'original_name' => $filename,
            'stored_path' => $path,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'type' => $type->value,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function findByUuid(string $uuid): Attachment
    {
        return $this->repository->findByUuidOrFail($uuid);
    }

    public function delete(string $uuid): bool
    {
        $attachment = $this->repository->findByUuidOrFail($uuid);

        // Delete the physical file
        Storage::disk($attachment->disk)->delete($attachment->stored_path);

        return $this->repository->delete($attachment->id);
    }

    /**
     * Get the download URL or stream for an attachment.
     */
    public function getDownloadUrl(string $uuid): string
    {
        $attachment = $this->repository->findByUuidOrFail($uuid);

        return Storage::disk($attachment->disk)->url($attachment->stored_path);
    }
}
