<?php

namespace App\Enums;

enum AbsenceType: string
{
    case Urlaub = 'urlaub';
    case Krank = 'krank';
    case Schulung = 'schulung';
    case Sonstige = 'sonstige';

    public function label(): string
    {
        return match ($this) {
            self::Urlaub => 'Urlaub',
            self::Krank => 'Krank',
            self::Schulung => 'Schulung',
            self::Sonstige => 'Sonstiges',
        };
    }
}
