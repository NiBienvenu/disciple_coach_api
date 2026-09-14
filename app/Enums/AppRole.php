<?php

namespace App\Enums;

enum AppRole: string
{
    case Disciple = 'disciple';
    case Coach = 'coach';
    case Admin = 'admin';
    case Editor = 'editor';
    case Mentor = 'mentor';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function selfAssignable(): array
    {
        return [
            self::Disciple->value,
            self::Coach->value,
        ];
    }

    /** @return list<string> */
    public static function staff(): array
    {
        return [
            self::Admin->value,
            self::Editor->value,
            self::Mentor->value,
        ];
    }
}
