<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Models\Message;
use Modules\MultiTenancy\Models\Workspace;
use Tests\TestCase;

class FileManagerDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EssentialPlatformSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserAndEmployeeSeeder::class,
        ]);
    }

    public function test_guest_is_redirected_from_drive(): void
    {
        $this->get('/admin/file-manager/drive')
            ->assertRedirect(route('login'));
    }

    public function test_drive_shows_image_preview_but_not_for_archives(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $employee = $boss->resolveEmployee();
        $workspace = $employee?->workspace ?? Workspace::query()->firstOrFail();

        Storage::fake('local');

        $imagePath = 'attachments/workspace/'.$workspace->id.'/photo.jpg';
        $zipPath = 'attachments/workspace/'.$workspace->id.'/bundle.zip';
        Storage::disk('local')->put($imagePath, 'fake-image-bytes');
        Storage::disk('local')->put($zipPath, 'fake-zip-bytes');

        $image = Attachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'attachable_type' => Workspace::class,
            'attachable_id' => $workspace->id,
            'original_name' => 'photo.jpg',
            'stored_path' => $imagePath,
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'type' => AttachmentType::Image->value,
            'processing_status' => 'ready',
            'uploaded_by' => $employee?->id,
        ]);

        $zip = Attachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'attachable_type' => Workspace::class,
            'attachable_id' => $workspace->id,
            'original_name' => 'bundle.zip',
            'stored_path' => $zipPath,
            'disk' => 'local',
            'mime_type' => 'application/zip',
            'size_bytes' => 14,
            'type' => AttachmentType::Archive->value,
            'processing_status' => 'ready',
            'uploaded_by' => $employee?->id,
        ]);

        $this->actingAs($boss);

        $response = $this->get('/admin/file-manager/drive')->assertOk();

        $imageMedia = route('attachments.media', ['uuid' => $image->uuid]);
        $zipMedia = route('attachments.media', ['uuid' => $zip->uuid]);

        $response->assertSee('photo.jpg')
            ->assertSee('bundle.zip')
            ->assertSee($imageMedia, false)
            ->assertSee('No preview')
            ->assertDontSee('src="'.$zipMedia.'"', false);
    }

    public function test_media_stream_allows_workspace_and_orphaned_attachments(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $employee = $boss->resolveEmployee();
        $workspace = $employee?->workspace ?? Workspace::query()->firstOrFail();

        Storage::fake('local');

        $workspacePath = 'attachments/workspace/'.$workspace->id.'/library.jpg';
        $orphanPath = 'attachments/message/99999/orphan.jpg';
        Storage::disk('local')->put($workspacePath, 'workspace-image-bytes');
        Storage::disk('local')->put($orphanPath, 'orphan-image-bytes');

        $library = Attachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'attachable_type' => Workspace::class,
            'attachable_id' => $workspace->id,
            'original_name' => 'library.jpg',
            'stored_path' => $workspacePath,
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 20,
            'type' => AttachmentType::Image->value,
            'processing_status' => 'ready',
            'uploaded_by' => $employee?->id,
        ]);

        // Message was deleted; attachable morph resolves to null.
        $orphan = Attachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'attachable_type' => Message::class,
            'attachable_id' => 99999,
            'original_name' => 'orphan.jpg',
            'stored_path' => $orphanPath,
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 18,
            'type' => AttachmentType::Image->value,
            'processing_status' => 'ready',
            'uploaded_by' => $employee?->id,
        ]);

        $this->actingAs($boss);

        $this->get(route('attachments.media', ['uuid' => $library->uuid]))
            ->assertOk();

        $this->get(route('attachments.media', ['uuid' => $orphan->uuid]))
            ->assertOk();
    }
}
