<?php

namespace App\Enums;

enum PreferredLanguage: string
{
    case Fr = 'fr';
    case Rn = 'rn';
    case En = 'en';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function resolve(?string $language): self
    {
        return self::tryFrom((string) $language) ?? self::Fr;
    }
}
