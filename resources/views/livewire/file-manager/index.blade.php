<div>
    <x-common.page-breadcrumb pageTitle="File Manager">
        <x-slot:subtitle>Browse, upload, and manage workspace attachments</x-slot:subtitle>
        <x-slot:actions>
            <a
                href="{{ route('file-manager.drive') }}"
                wire:navigate
                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]"
            >
                Open Drive
            </a>
            <button
                type="button"
                @click="$wire.showUploadModal = true; $wire.openUploadModal()"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Upload File
            </button>
        </x-slot:actions>
    </x-common.page-breadcrumb>


    {{-- All Media --}}
    <div class="mb-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">All Media</h3>
            <button
                type="button"
                wire:click="setTypeFilter(null)"
                class="text-sm font-medium text-brand-500 hover:text-brand-600 {{ $typeFilter ? '' : 'opacity-50 pointer-events-none' }}"
            >
                Clear filter
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($typeStats as $typeKey => $stat)
                @php
                    $tone = match ($typeKey) {
                        'image' => ['bg' => 'bg-success-50 dark:bg-success-500/10', 'text' => 'text-success-600 dark:text-success-400', 'bar' => 'bg-success-500'],
                        'video' => ['bg' => 'bg-error-50 dark:bg-error-500/10', 'text' => 'text-error-600 dark:text-error-400', 'bar' => 'bg-error-500'],
                        'voice' => ['bg' => 'bg-warning-50 dark:bg-warning-500/10', 'text' => 'text-warning-600 dark:text-warning-400', 'bar' => 'bg-warning-500'],
                        'pdf', 'document', 'spreadsheet' => ['bg' => 'bg-blue-light-50 dark:bg-blue-light-500/10', 'text' => 'text-blue-light-600 dark:text-blue-light-400', 'bar' => 'bg-blue-light-500'],
                        'archive' => ['bg' => 'bg-orange-50 dark:bg-orange-500/10', 'text' => 'text-orange-600 dark:text-orange-400', 'bar' => 'bg-orange-500'],
                        default => ['bg' => 'bg-gray-100 dark:bg-white/5', 'text' => 'text-gray-600 dark:text-gray-300', 'bar' => 'bg-gray-500'],
                    };
                    $active = $typeFilter === $typeKey;
                @endphp
                <button
                    type="button"
                    wire:click="setTypeFilter('{{ $typeKey }}')"
                    class="rounded-2xl border p-5 text-left transition hover:border-brand-300 dark:hover:border-brand-500 {{ $active ? 'border-brand-500 ring-2 ring-brand-500/20 bg-brand-50/40 dark:bg-brand-500/5' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]' }}"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $tone['bg'] }} {{ $tone['text'] }}">
                            @if ($typeKey === 'image')
                                <img src="/images/icons/file-image.svg" alt="" class="h-6 w-6 dark:hidden" />
                                <img src="/images/icons/file-image-dark.svg" alt="" class="hidden h-6 w-6 dark:block" />
                            @elseif ($typeKey === 'video')
                                <img src="/images/icons/file-video.svg" alt="" class="h-6 w-6 dark:hidden" />
                                <img src="/images/icons/file-video-dark.svg" alt="" class="hidden h-6 w-6 dark:block" />
                            @elseif (in_array($typeKey, ['pdf', 'document', 'spreadsheet'], true))
                                <img src="/images/icons/file-pdf.svg" alt="" class="h-6 w-6 dark:hidden" />
                                <img src="/images/icons/file-pdf-dark.svg" alt="" class="hidden h-6 w-6 dark:block" />
                            @else
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                            @endif
                        </div>
                        <span class="text-theme-xs font-medium text-gray-400">{{ $stat['percent'] }}% used</span>
                    </div>
                    <h4 class="mb-1 text-base font-semibold text-gray-800 dark:text-white/90">{{ $stat['label'] }}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['count'] }} files · {{ $stat['human'] }}</p>
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ max($stat['percent'], $stat['count'] > 0 ? 4 : 0) }}%"></div>
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Folders / type buckets --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">All Folders</h3>
                    <span class="text-sm text-gray-400">{{ $totalFiles }} total</span>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($typeStats->take(4) as $typeKey => $stat)
                        <button
                            type="button"
                            wire:click="setTypeFilter('{{ $typeKey }}')"
                            class="flex items-center gap-4 rounded-xl border border-gray-200 p-4 text-left transition hover:border-brand-300 dark:border-gray-800 dark:hover:border-brand-500"
                        >
                            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-gray-50 text-brand-500 dark:bg-white/5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 9.75h16.5m-16.5 0A2.25 2.25 0 0 1 6 7.5h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v7.5A2.25 2.25 0 0 0 6 19.5h12a2.25 2.25 0 0 0 2.25-2.25v-7.5"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h4 class="truncate font-medium text-gray-800 dark:text-white/90">{{ $stat['label'] }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['count'] }} Files · {{ $stat['human'] }}</p>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Recent files --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Files</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @if ($typeFilter)
                                Showing {{ $typeStats[$typeFilter]['label'] ?? $typeFilter }} files
                            @else
                                Latest uploads across the workspace
                            @endif
                        </p>
                    </div>
                    <div class="relative w-full sm:max-w-xs">
                        <svg class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m21 21-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z"/>
                        </svg>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search files..."
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-10 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-5 py-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:px-6">File Name</th>
                                <th class="px-5 py-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400">Category</th>
                                <th class="px-5 py-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400">Size</th>
                                <th class="px-5 py-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400">Date Modified</th>
                                <th class="px-5 py-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($files as $file)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3.5 sm:px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/5">
                                                @if (($file->type?->value ?? '') === 'image')
                                                    <img src="/images/icons/file-image.svg" class="h-5 w-5 dark:hidden" alt="" />
                                                    <img src="/images/icons/file-image-dark.svg" class="hidden h-5 w-5 dark:block" alt="" />
                                                @elseif (($file->type?->value ?? '') === 'video')
                                                    <img src="/images/icons/file-video.svg" class="h-5 w-5 dark:hidden" alt="" />
                                                    <img src="/images/icons/file-video-dark.svg" class="hidden h-5 w-5 dark:block" alt="" />
                                                @elseif (($file->type?->value ?? '') === 'pdf')
                                                    <img src="/images/icons/file-pdf.svg" class="h-5 w-5 dark:hidden" alt="" />
                                                    <img src="/images/icons/file-pdf-dark.svg" class="hidden h-5 w-5 dark:block" alt="" />
                                                @else
                                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $file->original_name }}</p>
                                                <p class="truncate text-theme-xs text-gray-400">{{ $file->uploader?->name ?? 'System' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                            {{ $file->type?->label() ?? 'Other' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">{{ $file->human_size }}</td>
                                    <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">{{ $file->updated_at?->format('d M, Y') }}</td>
                                    <td class="px-5 py-3.5 sm:px-6">
                                        <div class="flex items-center gap-2">
                                            <x-ui.button variant="ghost" size="xs" wire:click="download('{{ $file->uuid }}')">
                                                Download
                                            </x-ui.button>
                                            <x-ui.confirm-button
                                                heading="Delete this file?"
                                                message="It will be permanently removed."
                                                confirm-label="Delete"
                                                method="deleteFile"
                                                :params="[$file->uuid]"
                                                variant="danger-ghost"
                                                size="xs"
                                            >
                                                Delete
                                            </x-ui.confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-16 text-center sm:px-6">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
                                                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                            </div>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">No files yet</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload your first file to start building the library.</p>
                                            <button type="button" @click="$wire.showUploadModal = true; $wire.openUploadModal()" class="mt-4 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Upload File</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Storage details --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90">Storage Details</h3>
                <div class="mb-4 flex items-end justify-between gap-3">
                    <div>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $freeHuman }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Estimated free space left</p>
                    </div>
                    <span class="text-sm font-medium text-gray-400">{{ $usedPercent }}% used</span>
                </div>
                <div class="mb-5 h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-full rounded-full bg-brand-500" style="width: {{ max($usedPercent, $totalFiles > 0 ? 3 : 0) }}%"></div>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Total files</span>
                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $totalFiles }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Used storage</span>
                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $totalHuman }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-700 dark:bg-white/[0.02] md:p-6">
                <h4 class="mb-2 font-semibold text-gray-800 dark:text-white/90">Quick upload</h4>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Drop files into the library for tasks, conversations, and projects.</p>
                <button
                    type="button"
                    @click="$wire.showUploadModal = true; $wire.openUploadModal()"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                    Choose file
                </button>
            </div>
        </div>
    </div>

    <x-ui.slide-form-modal
        entangle="showUploadModal" loading-target="openUploadModal"
        title="Upload File"
        description="Stored in the workspace library for tasks, conversations, and projects."
        close-method="closeUploadModal"
        size="sm"
    >
        <form id="modal-upload-file" wire:submit="uploadFile" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">File</label>
                <input
                    type="file"
                    wire:model="upload"
                    class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-transparent text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200 dark:border-gray-700 dark:text-gray-300 dark:file:bg-white/5 dark:file:text-gray-300"
                />
                <div wire:loading wire:target="upload" class="mt-2 text-sm text-gray-500">Preparing file...</div>
                @error('upload') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                <p class="mt-2 text-theme-xs text-gray-400">Max 50MB. Images, docs, video, and archives supported.</p>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="closeUploadModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-upload-file" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="uploadFile">Upload</span>
                <span wire:loading wire:target="uploadFile">Saving...</span>
            </button>
        </x-slot:footer>
    </x-ui.slide-form-modal>
</div>
