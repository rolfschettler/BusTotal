<?php

namespace App\Enums;

enum EmploymentType: string
{
    case Vollzeit = 'vollzeit';
    case Teilzeit = 'teilzeit';
    case Aushilfe = 'aushilfe';
    case Extern = 'extern';

    public function label(): string
    {
        return match ($this) {
            self::Vollzeit => 'Vollzeit',
            self::Teilzeit => 'Teilzeit',
            self::Aushilfe => 'Aushilfe',
            self::Extern => 'Extern (Freiberufler)',
        };
    }
}
