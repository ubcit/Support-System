<?php

namespace App\Livewire\FileManager;

use App\Livewire\Concerns\AuthorizesActions;
use App\Livewire\FileManager\Concerns\ManagesWorkspaceAttachments;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Attachments\Models\Attachment;
use Modules\Attachments\Services\AttachmentService;

#[Title('Drive')]
class Drive extends Component
{
    use AuthorizesActions, ManagesWorkspaceAttachments, WithFileUploads, WithPagination;

    public string $search = '';

    public ?string $typeFilter = null;

    public ?string $selectedUuid = null;

    public bool $showPreview = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setTypeFilter(?string $type): void
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    public function openPreview(string $uuid, AttachmentService $service)
    {
        $attachment = $service->findByUuid($uuid);

        if (! $this->isPreviewable($attachment)) {
            $this->showPreview = false;
            $this->selectedUuid = null;

            return $this->download($uuid, $service);
        }

        $this->selectedUuid = $uuid;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->selectedUuid = null;
    }

    public function isPreviewable(Attachment $attachment): bool
    {
        return in_array($attachment->type?->value, ['image', 'video', 'voice', 'pdf'], true);
    }

    public function render()
    {
        $query = Attachment::query()
            ->with('uploader')
            ->latest();

        if ($this->search !== '') {
            $query->where('original_name', 'like', '%'.$this->search.'%');
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        $files = $query->paginate(24);

        $selected = null;
        if ($this->selectedUuid) {
            $selected = Attachment::query()
                ->where('uuid', $this->selectedUuid)
                ->first();
        }

        return view('livewire.file-manager.drive', [
            'files' => $files,
            'selected' => $selected,
            'types' => AttachmentType::cases(),
        ]);
    }
}
