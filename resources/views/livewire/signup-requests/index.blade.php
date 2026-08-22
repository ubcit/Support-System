<div>
    <x-common.page-breadcrumb pageTitle="Signup Requests" compact>
        <x-slot:subtitle>
            {{ $pendingUsers->count() }} pending {{ \Illuminate\Support\Str::plural('request', $pendingUsers->count()) }}
        </x-slot:subtitle>
    </x-common.page-breadcrumb>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4" :message="session('success')" />
    @endif
    @if (session('error'))
        <x-ui.alert variant="error" class="mb-4" :message="session('error')" />
    @endif
    @error('rejectMessage')
        <x-ui.alert variant="error" class="mb-4" :message="$message" />
    @enderror
    @error('approveRole')
        <x-ui.alert variant="error" class="mb-4" :message="$message" />
    @enderror

    @if ($pendingUsers->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center dark:border-gray-800 dark:bg-white/[0.03]">
            <x-heroicon-o-user-plus class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"/>
            <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No pending requests</h3>
            <p class="mt-1 text-sm text-gray-500">New signups waiting for approval will show up here.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($pendingUsers as $user)
                <div
                    wire:key="signup-request-{{ $user->id }}"
                    class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-sm font-bold uppercase text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white">{{ $user->name }}</h3>
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Pending</span>
                                </div>
                                <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($user->phone)
                                        <span>Phone: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $user->phone }}</span></span>
                                    @endif
                                    <span>Requested {{ $user->created_at?->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid w-full gap-3 lg:max-w-xl lg:grid-cols-2">
                            <div class="space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-white/5 dark:bg-white/5">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Assign role</label>
                                <select
                                    wire:model="approveRoleByUserId.{{ $user->id }}"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                >
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <x-ui.button
                                    class="w-full"
                                    wire:click="approve({{ $user->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="approve({{ $user->id }})"
                                >
                                    Approve
                                </x-ui.button>
                            </div>

                            <div class="space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-white/5 dark:bg-white/5">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Rejection message</label>
                                <textarea
                                    wire:model="rejectMessageByUserId.{{ $user->id }}"
                                    rows="2"
                                    placeholder="Explain why access is denied…"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                ></textarea>
                                <x-ui.button
                                    variant="danger"
                                    class="w-full"
                                    wire:click="reject({{ $user->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="reject({{ $user->id }})"
                                >
                                    Reject
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
