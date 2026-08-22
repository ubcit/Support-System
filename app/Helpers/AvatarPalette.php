<?php

namespace App\Helpers;

class AvatarPalette
{
    /**
     * Full Tailwind class strings so JIT can see every color.
     *
     * @var list<string>
     */
    public const CLASSES = [
        'bg-sky-500 text-white',
        'bg-violet-500 text-white',
        'bg-emerald-500 text-white',
        'bg-rose-500 text-white',
        'bg-indigo-500 text-white',
        'bg-teal-500 text-white',
        'bg-fuchsia-500 text-white',
        'bg-cyan-500 text-white',
        'bg-orange-500 text-white',
        'bg-blue-500 text-white',
        'bg-pink-500 text-white',
        'bg-lime-600 text-white',
    ];

    public static function classes(string|int|null $seed): string
    {
        $key = trim((string) $seed);

        if ($key === '') {
            return self::CLASSES[0];
        }

        return self::CLASSES[abs(crc32($key)) % count(self::CLASSES)];
    }

    public static function initials(string $name, int $max = 2): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, max(1, $max)) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }
}
