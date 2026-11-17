<?php

namespace App\Enums;

/** Führerscheinklassen, die für einen Busbetrieb eine Rolle spielen. Gespeichert als Liste von Schlüsseln. */
enum LicenseClass: string
{
    case B = 'B';
    case BE = 'BE';
    case C = 'C';
    case CE = 'CE';
    case D1 = 'D1';
    case D1E = 'D1E';
    case D = 'D';
    case DE = 'DE';

    public function label(): string
    {
        return match ($this) {
            self::B => 'B (Pkw)',
            self::BE => 'BE',
            self::C => 'C (Lkw)',
            self::CE => 'CE',
            self::D1 => 'D1 (Kleinbus)',
            self::D1E => 'D1E',
            self::D => 'D (Bus)',
            self::DE => 'DE (Bus mit Anhänger)',
        };
    }
}
