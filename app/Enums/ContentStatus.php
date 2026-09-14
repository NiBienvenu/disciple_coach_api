<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Published = 'published';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
