{{--
    Global confirm dialog. Replaces Livewire's native `wire:confirm` (window.confirm).

    Open from any Livewire view:
    $store.confirm.ask({ heading, message, confirmLabel, cancelLabel, onConfirm })
    or <x-ui.confirm-button method="..." :params="[...]">.
--}}
<div
    x-show="$store.confirm.open"
    x-cloak
    @keydown.escape.window="$store.confirm.open && $store.confirm.close()"
    class="fixed inset-0 z-[100000] flex items-end justify-center sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="$store.confirm.open ? 'confirm-dialog-title' : null"
>
    <div
        class="fixed inset-0 bg-gray-900/50 backdrop-blur-[2px]"
        @click="$store.confirm.close()"
    ></div>

    <div
        class="relative z-10 w-full max-w-md overflow-hidden rounded-t-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 sm:rounded-2xl"
        @click.stop
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="px-5 py-5 sm:px-6">
            <h3 id="confirm-dialog-title" class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="$store.confirm.heading"></h3>
            <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400" x-show="$store.confirm.message" x-text="$store.confirm.message"></p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 bg-gray-50/90 px-5 py-4 dark:border-gray-800 dark:bg-white/[0.02] sm:px-6">
            <button
                type="button"
                @click="$store.confirm.close()"
                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                x-text="$store.confirm.cancelLabel"
            ></button>
            <button
                type="button"
                @click="$store.confirm.confirm()"
                class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600"
                x-text="$store.confirm.confirmLabel"
            ></button>
        </div>
    </div>
</div>
