<div>
    <x-common.page-breadcrumb pageTitle="Drive">
        <x-slot:subtitle>Browse workspace files with inline previews</x-slot:subtitle>
        <x-slot:actions>
            <a
                href="{{ route('file-manager') }}"
                wire:navigate
                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]"
            >
                Library
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

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="setTypeFilter(null)"
                class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $typeFilter === null ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' }}"
            >
                All
            </button>
            @foreach ($types as $type)
                <button
                    type="button"
                    wire:click="setTypeFilter('{{ $type->value }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $typeFilter === $type->value ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' }}"
                >
                    {{ $type->label() }}
                </button>
            @endforeach
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

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        @forelse ($files as $file)
            @php
                $typeValue = $file->type?->value ?? 'other';
                $mediaUrl = route('attachments.media', ['uuid' => $file->uuid]);
                $previewable = in_array($typeValue, ['image', 'video', 'voice', 'pdf'], true);
            @endphp
            <div class="group overflow-hidden rounded-2xl border border-gray-200 bg-white transition hover:border-brand-300 hover:shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500">
                <button
                    type="button"
                    wire:click="openPreview('{{ $file->uuid }}')"
                    class="block w-full text-left"
                >
                    <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-gray-50 dark:bg-white/[0.04]">
                        @if ($typeValue === 'image')
                            <img
                                src="{{ $mediaUrl }}"
                                alt="{{ $file->original_name }}"
                                loading="lazy"
                                class="h-full w-full object-cover"
                            />
                        @elseif ($typeValue === 'video')
                            <video
                                src="{{ $mediaUrl }}"
                                class="h-full w-full object-cover bg-black"
                                muted
                                playsinline
                                preload="metadata"
                            ></video>
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/20">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-gray-800 shadow">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </div>
                        @elseif ($typeValue === 'voice')
                            <div class="flex flex-col items-center gap-2 text-warning-600 dark:text-warning-400">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z"/></svg>
                                <span class="text-theme-xs font-medium">Audio</span>
                            </div>
                        @elseif ($typeValue === 'pdf')
                            <img src="/images/icons/file-pdf.svg" class="h-12 w-12 dark:hidden" alt="" />
                            <img src="/images/icons/file-pdf-dark.svg" class="hidden h-12 w-12 dark:block" alt="" />
                        @else
                            <svg class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        @endif
                    </div>

                    <div class="space-y-1 p-3">
                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90" title="{{ $file->original_name }}">
                            {{ $file->original_name }}
                        </p>
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-theme-xs text-gray-400">{{ $file->type?->label() ?? 'Other' }}</span>
                            <span class="shrink-0 text-theme-xs text-gray-400">{{ $file->human_size }}</span>
                        </div>
                    </div>
                </button>

                <div class="flex items-center gap-1 border-t border-gray-100 px-2 py-1.5 dark:border-gray-800">
                    <x-ui.button variant="ghost" size="xs" wire:click="download('{{ $file->uuid }}')" class="!px-2">
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
                        class="!px-2"
                    >
                        Delete
                    </x-ui.confirm-button>
                    @if (! $previewable)
                        <span class="ml-auto pr-1 text-[10px] uppercase tracking-wide text-gray-400">No preview</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-16 text-center dark:border-gray-700 dark:bg-white/[0.02]">
                <div class="mx-auto flex max-w-sm flex-col items-center">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">No files yet</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload files to browse them in Drive with previews.</p>
                    <button type="button" @click="$wire.showUploadModal = true; $wire.openUploadModal()" class="mt-4 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Upload File</button>
                </div>
            </div>
        @endforelse
    </div>

    @if ($files->hasPages())
        <div class="mt-6">
            {{ $files->links() }}
        </div>
    @endif

    @if ($selected)
        @php $previewUrl = route('attachments.media', ['uuid' => $selected->uuid]); @endphp
        <div
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5"
            wire:keydown.escape.window="closePreview"
        >
            <div
                class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                wire:click="closePreview"
            ></div>

            <div class="relative w-full max-w-4xl rounded-3xl bg-white p-5 shadow-xl dark:bg-gray-900 sm:p-6">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $selected->original_name }}</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            {{ $selected->type?->label() ?? 'Other' }} · {{ $selected->human_size }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closePreview"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:hover:text-white"
                        aria-label="Close preview"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl bg-gray-50 dark:bg-black/40">
                    @if (($selected->type?->value ?? '') === 'image')
                        <img src="{{ $previewUrl }}" alt="{{ $selected->original_name }}" class="mx-auto max-h-[70vh] w-auto object-contain" />
                    @elseif (($selected->type?->value ?? '') === 'video')
                        <video src="{{ $previewUrl }}" controls playsinline class="mx-auto max-h-[70vh] w-full bg-black"></video>
                    @elseif (($selected->type?->value ?? '') === 'voice')
                        <div class="space-y-4 p-6">
                            <audio controls class="w-full">
                                <source src="{{ $previewUrl }}" type="{{ $selected->mime_type }}">
                            </audio>
                            @if ($selected->ai_transcript)
                                <div class="rounded-lg border border-purple-200 bg-purple-50 p-3 text-sm dark:border-purple-800 dark:bg-purple-950">
                                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Transcript</span>
                                    <p class="italic text-gray-700 dark:text-gray-300">{{ $selected->ai_transcript }}</p>
                                </div>
                            @endif
                        </div>
                    @elseif (($selected->type?->value ?? '') === 'pdf')
                        <iframe src="{{ $previewUrl }}" title="{{ $selected->original_name }}" class="h-[70vh] w-full border-0"></iframe>
                    @endif
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <x-ui.button variant="outline" size="sm" wire:click="download('{{ $selected->uuid }}')">
                        Download
                    </x-ui.button>
                    <button type="button" wire:click="closePreview" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-ui.slide-form-modal
        entangle="showUploadModal" loading-target="openUploadModal"
        title="Upload File"
        description="Stored in the workspace library for tasks, conversations, and projects."
        close-method="closeUploadModal"
        size="sm"
    >
        <form id="modal-upload-file-drive" wire:submit="uploadFile" class="space-y-4">
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
            <button type="submit" form="modal-upload-file-drive" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="uploadFile">Upload</span>
                <span wire:loading wire:target="uploadFile">Saving...</span>
            </button>
        </x-slot:footer>
    </x-ui.slide-form-modal>
</div>
