<?php

use App\Helpers\UiButton;

test('danger icon buttons sit on the dark surface instead of a light fill', function () {
    $classes = UiButton::classes('danger', 'icon');

    expect($classes)
        ->toContain('h-9 w-9')
        ->toContain('dark:bg-gray-800')
        ->toContain('dark:text-red-400')
        ->not->toContain('bg-red-50 hover:bg-red-100');
});

test('outline icon buttons match danger chrome in dark mode', function () {
    $outline = UiButton::classes('outline', 'icon');
    $danger = UiButton::classes('danger', 'icon');

    expect($outline)->toContain('dark:bg-gray-800');
    expect($danger)->toContain('dark:bg-gray-800');
    expect($outline)->toContain('h-9 w-9');
    expect($danger)->toContain('h-9 w-9');
});
