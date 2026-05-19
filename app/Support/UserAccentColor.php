<?php

namespace App\Support;

final class UserAccentColor
{
    public const DEFAULT = 'blue';

    /** @var list<string> */
    public const ALLOWED = [
        'blue',
        'red',
        'green',
        'orange',
        'yellow',
        'light_black',
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'blue' => 'Blue',
            'red' => 'Red',
            'green' => 'Green',
            'orange' => 'Orange',
            'yellow' => 'Yellow',
            'light_black' => 'Light black',
        ];
    }

    public static function resolve(?string $value): string
    {
        if ($value === null || $value === '') {
            return self::DEFAULT;
        }

        return in_array($value, self::ALLOWED, true) ? $value : self::DEFAULT;
    }

    public static function isAllowed(?string $value): bool
    {
        return $value !== null && in_array($value, self::ALLOWED, true);
    }
}
