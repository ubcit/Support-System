<div>
    <x-common.page-breadcrumb pageTitle="Profile">
        <x-slot:subtitle>Manage your account settings</x-slot:subtitle>
    </x-common.page-breadcrumb>

    @if ($statusMessage)
        <div class="mb-4">
            <x-ui.alert variant="success" :message="$statusMessage" />
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-7">Profile</h3>

        <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800 lg:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex w-full flex-col items-center gap-4 sm:flex-row">
                    <div class="relative">
                        <label class="group relative inline-block cursor-pointer">
                            <span class="sr-only">Change profile photo</span>
                            @if ($avatar)
                                <img src="{{ $avatar->temporaryUrl() }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover ring-2 ring-brand-200 dark:ring-brand-800" />
                            @elseif ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover ring-2 ring-transparent group-hover:ring-brand-400" />
                            @else
                                <x-ui.person-avatar :person="$user" size="3xl" />
                            @endif
                            <span class="absolute inset-0 flex flex-col items-center justify-center rounded-full bg-black/55 text-white opacity-0 transition group-hover:opacity-100">
                                <x-heroicon-o-camera class="h-5 w-5" />
                                <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide">Change</span>
                            </span>
                            <input type="file" wire:model="avatar" accept="image/*" class="sr-only" />
                        </label>
                        <div wire:loading wire:target="avatar" class="mt-1 text-center text-theme-xs text-gray-400">Uploading…</div>
                        @error('avatar') <p class="mt-1 text-center text-sm text-error-500">{{ $message }}</p> @enderror
                        @if ($user->avatarUrl() && ! $avatar)
                            <button type="button" wire:click="removeAvatar" class="mt-2 block w-full text-center text-theme-xs font-medium text-gray-400 hover:text-error-500">
                                Remove photo
                            </button>
                        @endif
                    </div>
                    <div class="order-3 text-center sm:order-2 sm:text-left">
                        <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                        <div class="mt-2 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            @if ($roleLabel)
                                <x-ui.badge color="primary" size="sm">{{ $roleLabel }}</x-ui.badge>
                            @endif
                            @if ($workspaceName)
                                <span class="text-theme-xs text-gray-400">Workspace: {{ $workspaceName }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800 lg:p-6">
                <h4 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90">Personal information</h4>
                <form wire:submit="saveProfile" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                        <input type="text" wire:model="name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('name') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                        <input type="email" wire:model="email" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('email') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-theme-xs text-gray-400">Click your photo above to upload a new profile image. It saves as soon as you pick a file.</p>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveProfile">Save Profile</span>
                        <span wire:loading wire:target="saveProfile">Saving…</span>
                    </button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800 lg:p-6">
                <h4 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90">Change password</h4>
                <form wire:submit="changePassword" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Current Password</label>
                        <input type="password" wire:model="current_password" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('current_password') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">New Password</label>
                        <input type="password" wire:model="password" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('password') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Confirm Password</label>
                        <input type="password" wire:model="password_confirmation" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="changePassword">Update Password</span>
                        <span wire:loading wire:target="changePassword">Updating…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
