@props([
    'person' => null,
    'name' => null,
    'src' => null,
    'seed' => null,
    'size' => 'md',
    'ring' => false,
])

@php
    $displayName = $name ?? (is_object($person) ? (string) ($person->name ?? '') : '');
    $avatarSrc = $src ?? (is_object($person) && method_exists($person, 'avatarUrl') ? $person->avatarUrl() : null);
    $seed = $seed ?? (is_object($person) ? ($person->id ?? $displayName) : $displayName);
    $color = is_object($person) && method_exists($person, 'avatarColorClasses')
        ? $person->avatarColorClasses()
        : \App\Helpers\AvatarPalette::classes($seed);
    $letterCount = in_array($size, ['lg', 'xl', '2xl', '3xl'], true) ? 2 : 1;
    $initials = \App\Helpers\AvatarPalette::initials($displayName, $letterCount);

    $sizeClass = match ($size) {
        'xs' => 'h-4 w-4 text-[8px]',
        'sm' => 'h-5 w-5 text-[9px]',
        'lg' => 'h-8 w-8 text-xs',
        'xl' => 'h-10 w-10 text-sm',
        '2xl' => 'h-14 w-14 text-xl',
        '3xl' => 'h-20 w-20 text-2xl',
        default => 'h-6 w-6 text-[10px]',
    };
@endphp

<span
    title="{{ $displayName }}"
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-bold',
        $sizeClass,
        'ring-2 ring-white dark:ring-gray-900' => $ring,
        $avatarSrc ? 'bg-gray-100 dark:bg-gray-800' : $color,
    ]) }}
>
    @if ($avatarSrc)
        <img src="{{ $avatarSrc }}" alt="{{ $displayName }}" class="h-full w-full object-cover" />
    @else
        {{ $initials }}
    @endif
</span>
