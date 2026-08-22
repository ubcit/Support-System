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

<button
    type="{{ $attributes->get('type', 'button') }}"
    {{ $attributes->except('type')->class(\App\Helpers\UiButton::classes($variant, $size)) }}
    @click.prevent="$store.confirm.ask({
        heading: {{ \Illuminate\Support\Js::from($heading) }},
        message: {{ \Illuminate\Support\Js::from($message) }},
        confirmLabel: {{ \Illuminate\Support\Js::from($confirmLabel) }},
        cancelLabel: {{ \Illuminate\Support\Js::from($cancelLabel) }},
        onConfirm: () => $wire.call({{ \Illuminate\Support\Js::from($method) }}, ...{{ \Illuminate\Support\Js::from(array_values($params)) }})
    })"
>
    {{ $slot }}
</button>
