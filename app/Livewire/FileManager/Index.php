<?php

namespace App\Livewire\FileManager;

use App\Livewire\Concerns\AuthorizesActions;
use App\Livewire\FileManager\Concerns\ManagesWorkspaceAttachments;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Attachments\Models\Attachment;

#[Title('File Manager')]
class Index extends Component
{
    use AuthorizesActions, ManagesWorkspaceAttachments, WithFileUploads;

    public string $search = '';

    public ?string $typeFilter = null;

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

        $files = $query->limit(100)->get();

        $all = Attachment::query()->get(['type', 'size_bytes']);
        $totalBytes = (int) $all->sum('size_bytes');
        $totalFiles = $all->count();

        $typeStats = collect(AttachmentType::cases())->mapWithKeys(function (AttachmentType $type) use ($all, $totalBytes) {
            $items = $all->filter(fn (Attachment $attachment) => $attachment->type === $type);
            $bytes = (int) $items->sum('size_bytes');

            return [
                $type->value => [
                    'label' => $type->label(),
                    'count' => $items->count(),
                    'bytes' => $bytes,
                    'human' => $this->formatBytes($bytes),
                    'percent' => $totalBytes > 0 ? (int) round(($bytes / $totalBytes) * 100) : 0,
                ],
            ];
        });

        $storageCap = max($totalBytes * 2, 10 * 1024 * 1024 * 1024); // soft visual capacity
        $usedPercent = $storageCap > 0 ? min(100, (int) round(($totalBytes / $storageCap) * 100)) : 0;

        return view('livewire.file-manager.index', [
            'files' => $files,
            'typeStats' => $typeStats,
            'totalBytes' => $totalBytes,
            'totalHuman' => $this->formatBytes($totalBytes),
            'totalFiles' => $totalFiles,
            'freeHuman' => $this->formatBytes(max(0, $storageCap - $totalBytes)),
            'usedPercent' => $usedPercent,
            'types' => AttachmentType::cases(),
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) max($bytes, 0);

        for ($i = 0; $value >= 1024 && $i < count($units) - 1; $i++) {
            $value /= 1024;
        }

        return round($value, $value >= 10 || $i === 0 ? 0 : 2).' '.$units[$i];
    }
}
