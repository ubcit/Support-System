@props([
    'status' => 'default',
])

@php
    $normalized = strtolower((string) $status);
    $map = [
        'healthy' => ['success', 'Healthy'],
        'success' => ['success', 'Success'],
        'passed' => ['success', 'Passed'],
        'active' => ['success', 'Active'],
        'optimal' => ['success', 'Optimal'],
        'open' => ['info', 'Open'],
        'running' => ['warning', 'Running'],
        'pending' => ['warning', 'Pending'],
        'warning' => ['warning', 'Warning'],
        'heavy load' => ['warning', 'Heavy Load'],
        'failed' => ['error', 'Failed'],
        'error' => ['error', 'Error'],
        'down' => ['error', 'Down'],
        'inactive' => ['error', 'Inactive'],
        'canceled' => ['error', 'Canceled'],
        'cancelled' => ['error', 'Cancelled'],
    ];

    [$color, $label] = $map[$normalized] ?? ['light', ucfirst(str_replace('_', ' ', (string) $status))];
@endphp

<x-ui.badge :color="$color" size="sm" {{ $attributes }}>
    {{ $slot->isEmpty() ? $label : $slot }}
</x-ui.badge>
