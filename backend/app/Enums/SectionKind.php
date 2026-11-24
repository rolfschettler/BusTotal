<?php

namespace App\Enums;

/** Art eines Abschnitts im Reisevorhaben. */
enum SectionKind: string
{
    case Hinfahrt = 'hinfahrt';
    case VorOrt = 'vor_ort';
    case Rueckfahrt = 'rueckfahrt';

    public function label(): string
    {
        return match ($this) {
            self::Hinfahrt => 'Hinfahrt',
            self::VorOrt => 'Fahrt vor Ort',
            self::Rueckfahrt => 'Rückfahrt',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}
