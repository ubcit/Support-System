<?php

namespace App\Livewire\FileManager\Concerns;

use Modules\Attachments\Services\AttachmentService;
use Modules\MultiTenancy\Models\Workspace;

trait ManagesWorkspaceAttachments
{
    public bool $showUploadModal = false;

    public $upload;

    public function openUploadModal(): void
    {
        $this->resetValidation();
        $this->upload = null;
        $this->showUploadModal = true;
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->upload = null;
        $this->resetValidation();
    }

    public function uploadFile(AttachmentService $service): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'max:51200'],
        ]);

        $employee = auth()->user()?->resolveEmployee();
        $workspace = $employee?->workspace;

        if (! $workspace) {
            $this->addError('upload', 'No workspace found to attach files to.');

            return;
        }

        $service->store(
            file: $this->upload,
            attachableType: Workspace::class,
            attachableId: $workspace->id,
            uploadedBy: $employee?->id,
        );

        $this->closeUploadModal();
        session()->flash('success', 'File uploaded successfully.');
    }

    public function setTypeFilter(?string $type): void
    {
        $this->typeFilter = $type;
    }

    public function deleteFile(string $uuid, AttachmentService $service): void
    {
        $this->authorizePermission('tasks.delete');
        $service->delete($uuid);
        session()->flash('success', 'File deleted.');
    }

    public function download(string $uuid, AttachmentService $service)
    {
        $service->findByUuid($uuid);

        return $this->redirect(route('attachments.download', ['uuid' => $uuid]));
    }
}
