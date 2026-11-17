<?php

namespace App\Enums;

enum VehicleType: string
{
    case Reisebus = 'reisebus';
    case Doppeldecker = 'doppeldecker';
    case Midibus = 'midibus';
    case Minibus = 'minibus';
    case Sonstige = 'sonstige';

    public function label(): string
    {
        return match ($this) {
            self::Reisebus => 'Reisebus',
            self::Doppeldecker => 'Doppeldeckerbus',
            self::Midibus => 'Midibus',
            self::Minibus => 'Minibus / Kleinbus',
            self::Sonstige => 'Sonstiges',
        };
    }
}
