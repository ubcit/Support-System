<div>
    <x-common.page-breadcrumb pageTitle="Customer AI Limits">
        <x-slot:subtitle>Daily AI spend caps. When a customer’s balance is empty, new messages go to manual review instead of AI.</x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('reports-hub', ['tab' => 'ai-cost']) }}" class="{{ \App\Helpers\UiButton::classes('outline') }}">AI Cost Report</a>
        </x-slot:actions>
    </x-common.page-breadcrumb>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Workspace default</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Applies to customers with a blank custom limit. Leave empty for unlimited AI until a customer has their own cap.</p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Daily limit (USD)</label>
                    <input type="number" step="0.01" min="0" wire:model="defaultLimit" placeholder="Unlimited" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    @error('defaultLimit')<p class="mt-1 text-sm text-error-500">{{ $message }}</p>@enderror
                </div>
                <button type="button" wire:click="saveDefault" class="{{ \App\Helpers\UiButton::classes('primary') }}">Save default</button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Per customer</h3>
                <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Search customers..." class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm sm:w-64 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Daily limit (USD)</th>
                            <th class="px-4 py-3">Spent today</th>
                            <th class="px-4 py-3">Remaining</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Budget reviews today</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            @php $customer = $row['customer']; @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $customer->name }}</div>
                                    @if ($row['uses_default'])
                                        <div class="text-xs text-gray-500">Using workspace default</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <input type="number" step="0.01" min="0" wire:model="customerLimits.{{ $customer->id }}" wire:blur="saveCustomer({{ $customer->id }})" placeholder="{{ $row['effective_limit'] === null ? 'Unlimited' : number_format($row['effective_limit'], 2) }}" class="h-9 w-28 rounded-lg border border-gray-300 px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                        <button type="button" wire:click="saveCustomer({{ $customer->id }})" class="{{ \App\Helpers\UiButton::classes('ghost', 'xs') }}">Save</button>
                                    </div>
                                    @error('customerLimits.'.$customer->id)<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">${{ number_format($row['spent'], 4) }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $row['remaining'] === null ? 'Unlimited' : '$'.number_format($row['remaining'], 4) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row['exhausted'])
                                        <span class="rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">Limit reached</span>
                                    @else
                                        <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">AI on</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['review_count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No customers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
