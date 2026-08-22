{{--
    Global toast/notification stack.

    Replaces the old pattern of every page independently checking
    `session('success')`/`session('error')` in its own blade template (most
    pages didn't render it at all, so most flash messages were invisible).
    `AppServiceProvider::registerFlashToastBridge()` converts any pending
    session flash into a `toast` browser event on every Livewire dehydrate;
    this listens for it here, once, at the app-shell level, so it survives
    `wire:navigate` and works from any page without per-page markup.

    New code can also dispatch directly: $this->dispatch('toast', type:
    'success', message: '...'); type is one of success|error|warning|info.
--}}
<div
    x-data="{
        toasts: [],
        nextId: 1,
        push(type, message) {
            const id = this.nextId++;
            this.toasts.push({ id, type, message });
            setTimeout(() => this.dismiss(id), 5000);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
    }"
    x-on:toast.window="push($event.detail.type ?? 'info', $event.detail.message ?? '')"
    class="fixed top-4 right-4 z-[9999] flex w-full max-w-sm flex-col gap-2"
    aria-live="polite"
    aria-atomic="true"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="flex items-start gap-3 rounded-lg border p-4 shadow-lg backdrop-blur-sm"
            :class="{
                'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/80 dark:border-emerald-800 dark:text-emerald-300': toast.type === 'success',
                'bg-red-50 border-red-200 text-red-800 dark:bg-red-950/80 dark:border-red-800 dark:text-red-300': toast.type === 'error',
                'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-950/80 dark:border-amber-800 dark:text-amber-300': toast.type === 'warning',
                'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-950/80 dark:border-blue-800 dark:text-blue-300': toast.type === 'info',
            }"
            role="alert"
        >
            <template x-if="toast.type === 'success'">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
            </template>
            <template x-if="toast.type === 'error'">
                <x-heroicon-o-x-circle class="h-5 w-5 shrink-0" />
            </template>
            <template x-if="toast.type === 'warning'">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
            </template>
            <template x-if="toast.type === 'info'">
                <x-heroicon-o-information-circle class="h-5 w-5 shrink-0" />
            </template>

            <p class="flex-1 text-sm font-medium" x-text="toast.message"></p>

            <button
                type="button"
                x-on:click="dismiss(toast.id)"
                class="shrink-0 rounded p-0.5 opacity-70 hover:opacity-100"
                aria-label="Dismiss notification"
            >
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>
    </template>
</div>
