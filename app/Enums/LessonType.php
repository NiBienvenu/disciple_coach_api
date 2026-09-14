<?php

namespace App\Enums;

enum LessonType: string
{
    case Main = 'main';
    case Supplementary = 'supplementary';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
