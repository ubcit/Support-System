<div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
    <div class="relative flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto w-full max-w-md pt-10">
                <a href="{{ route('login') }}" wire:navigate
                   class="inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back to sign in
                </a>
            </div>

            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                <div class="mb-5 sm:mb-8">
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">Create account</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Your account will be reviewed by admin before you can sign in.
                    </p>
                </div>

                @if (session('status'))
                    <div class="mb-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-800 dark:bg-success-900/20 dark:text-success-400">
                        {{ session('status') }}
                    </div>
                @endif

                <form wire:submit="register" class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Name<span class="text-error-500">*</span>
                        </label>
                        <input type="text"
                               wire:model="name"
                               autocomplete="name"
                               placeholder="John Doe"
                               class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('name') <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Email<span class="text-error-500">*</span>
                        </label>
                        <input type="email"
                               wire:model="email"
                               autocomplete="username"
                               placeholder="user@example.com"
                               class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('email') <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Phone number<span class="text-error-500">*</span>
                        </label>
                        <input type="tel"
                               wire:model="phone"
                               autocomplete="tel"
                               placeholder="+9647700000000"
                               class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('phone') <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Password<span class="text-error-500">*</span>
                        </label>
                        <input type="password"
                               wire:model="password"
                               autocomplete="new-password"
                               placeholder="Enter your password"
                               class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('password') <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Confirm Password<span class="text-error-500">*</span>
                        </label>
                        <input type="password"
                               wire:model="password_confirmation"
                               autocomplete="new-password"
                               placeholder="Confirm your password"
                               class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white transition disabled:opacity-60">
                        <span wire:loading.remove wire:target="register">Create account</span>
                        <span wire:loading wire:target="register" class="inline-flex items-center gap-1.5">
                            <x-ui.spinner size="sm" />
                            Creating…
                        </span>
                    </button>

                    @if ($errors->has('password_confirmation'))
                        <p class="text-sm text-error-500">{{ $errors->first('password_confirmation') }}</p>
                    @endif
                </form>
            </div>
        </div>

        <div class="bg-brand-950 relative hidden h-full w-full items-center lg:grid lg:w-1/2 dark:bg-white/5">
            <div class="z-1 flex items-center justify-center">
                <x-common.common-grid-shape/>
                <div class="flex max-w-xs flex-col items-center">
                    <a href="{{ url('/') }}" class="mb-4 block">
                        <img src="{{ asset('images/logo/auth-logo.svg') }}" alt="The Space" />
                    </a>
                    <p class="text-center text-gray-400 dark:text-white/60">
                        Join The Space and request access for your team.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

