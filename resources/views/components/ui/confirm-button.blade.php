@props([
    'heading' => 'Are you sure?',
    'message' => '',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'method',
    'params' => [],
    'variant' => 'danger',
    'size' => 'xs',
])

@php
    $paramList = array_values($params);
    $wireTarget = $method;
    if ($paramList !== []) {
        $wireTarget .= '('.collect($paramList)->map(function ($param) {
            if (is_bool($param)) {
                return $param ? 'true' : 'false';
            }
            if (is_numeric($param)) {
                return (string) $param;
            }
            if ($param === null) {
                return 'null';
            }

            return "'".addslashes((string) $param)."'";
        })->implode(', ').')';
    }
@endphp

<button
    type="{{ $attributes->get('type', 'button') }}"
    wire:loading.attr="disabled"
    wire:target="{{ $wireTarget }}"
    {{ $attributes->except('type')->class(\App\Helpers\UiButton::classes($variant, $size).' relative disabled:pointer-events-none disabled:opacity-60') }}
    @click.prevent="$store.confirm.ask({
        heading: {{ \Illuminate\Support\Js::from($heading) }},
        message: {{ \Illuminate\Support\Js::from($message) }},
        confirmLabel: {{ \Illuminate\Support\Js::from($confirmLabel) }},
        cancelLabel: {{ \Illuminate\Support\Js::from($cancelLabel) }},
        onConfirm: () => $wire.call({{ \Illuminate\Support\Js::from($method) }}, ...{{ \Illuminate\Support\Js::from($paramList) }})
    })"
>
    <span class="inline-flex items-center justify-center gap-1.5" wire:loading.remove wire:target="{{ $wireTarget }}">
        {{ $slot }}
    </span>
    <span class="inline-flex items-center justify-center" wire:loading wire:target="{{ $wireTarget }}">
        <x-ui.spinner size="sm" />
    </span>
</button>
