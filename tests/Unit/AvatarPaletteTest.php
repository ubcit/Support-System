<?php

use App\Helpers\AvatarPalette;

test('avatar palette assigns stable colors and varies by seed', function () {
    $first = AvatarPalette::classes(1);
    $second = AvatarPalette::classes(2);

    expect($first)->toBeIn(AvatarPalette::CLASSES);
    expect(AvatarPalette::classes(1))->toBe($first);
    expect($second)->not->toBe($first);
});

test('avatar initials take the first letters of a name', function () {
    expect(AvatarPalette::initials('Sara Ahmed', 1))->toBe('S');
    expect(AvatarPalette::initials('Sara Ahmed', 2))->toBe('SA');
    expect(AvatarPalette::initials('', 1))->toBe('?');
});
