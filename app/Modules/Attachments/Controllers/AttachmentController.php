<?php

namespace Modules\Attachments\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Attachments\Models\Attachment;
use Modules\Attachments\Services\AttachmentService;

class AttachmentController extends Controller
{
    // Allow-lists (ALLOWED_ATTACHABLE_TYPES / ALLOWED_EXTENSIONS) live on
    // AttachmentService so the Livewire UI upload path (App\Livewire\
    // TaskDetail) can enforce the exact same rules instead of drifting.

    public function __construct(
        protected AttachmentService $service
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:'.AttachmentService::ALLOWED_EXTENSIONS], // 50MB max
            'attachable_type' => ['required', 'string', Rule::in(AttachmentService::ALLOWED_ATTACHABLE_TYPES)],
            'attachable_id' => ['required', 'integer'],
        ]);

        Gate::authorize('create', [Attachment::class, $request->string('attachable_type')->toString()]);

        $attachableClass = $request->string('attachable_type')->toString();

        if (! $attachableClass::whereKey($request->integer('attachable_id'))->exists()) {
            throw ValidationException::withMessages([
                'attachable_id' => ['The selected attachable_id is invalid for this attachable_type.'],
            ]);
        }

        $attachment = $this->service->store(
            file: $request->file('file'),
            attachableType: $attachableClass,
            attachableId: $request->integer('attachable_id'),
            // Attachment::uploader() is belongsTo(Employee::class,
            // 'uploaded_by'), so this must be the Employee id, not the User
            // id -- passing $request->user()->id silently attributed every
            // API-uploaded attachment to whichever Employee happened to
            // share that numeric id (or none at all).
            uploadedBy: $request->user()?->resolveEmployee()?->id,
        );

        return response()->json([
            'message' => 'File uploaded successfully',
            'data' => [
                'uuid' => $attachment->uuid,
                'original_name' => $attachment->original_name,
                'type' => $attachment->type->value,
                'size' => $attachment->human_size,
                'mime_type' => $attachment->mime_type,
            ],
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $attachment = $this->service->findByUuid($uuid);

        Gate::authorize('view', $attachment);

        return response()->json([
            'data' => [
                'uuid' => $attachment->uuid,
                'original_name' => $attachment->original_name,
                'type' => $attachment->type->value,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->human_size,
                'size_bytes' => $attachment->size_bytes,
                'ai_transcript' => $attachment->ai_transcript,
                'created_at' => $attachment->created_at?->toISOString(),
            ],
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $attachment = $this->service->findByUuid($uuid);

        Gate::authorize('delete', $attachment);

        $this->service->delete($uuid);

        return response()->json(['message' => 'Attachment deleted successfully']);
    }

    public function download(string $uuid)
    {
        $attachment = $this->service->findByUuid($uuid);

        Gate::authorize('view', $attachment);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($attachment->disk);

        return $disk->download(
            $attachment->stored_path,
            $attachment->original_name
        );
    }

    /**
     * Stream the attachment inline so browsers can preview media.
     *
     * For images/videos this enables <img>/<video> previews.
     */
    public function media(string $uuid): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $attachment = $this->service->findByUuid($uuid);

        Gate::authorize('view', $attachment);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($attachment->disk);

        return $disk->response(
            $attachment->stored_path,
            $attachment->original_name,
            // Force inline disposition for in-browser preview.
            disposition: 'inline'
        );
    }
}
