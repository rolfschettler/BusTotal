<?php

namespace App\Enums;

/**
 * Art der Kosten, die unterwegs anfallen. Sie bestimmt die Vorbelegung „Reisevorleistung“ (Leistung eines Dritten, die den
 * Reisenden unmittelbar zugutekommt, § 25 UStG). Betriebskosten des Busses sind keine.
 * Grundlage: docs/fachlich/loesungskonzept-abrechnung.md Abschnitt 1.4.
 */
enum CostCategory: string
{
    case Maut = 'maut';
    case Parken = 'parken';
    case Faehre = 'faehre';
    case Treibstoff = 'treibstoff';
    case Reparatur = 'reparatur';
    case Hotel = 'hotel';
    case Verpflegung = 'verpflegung';
    case Eintritt = 'eintritt';
    case Reiseleiter = 'reiseleiter';
    case Fremdbus = 'fremdbus';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Maut => 'Maut',
            self::Parken => 'Parken',
            self::Faehre => 'Fähre',
            self::Treibstoff => 'Treibstoff',
            self::Reparatur => 'Reparatur',
            self::Hotel => 'Hotel',
            self::Verpflegung => 'Verpflegung',
            self::Eintritt => 'Eintritt',
            self::Reiseleiter => 'Reiseleiter (extern)',
            self::Fremdbus => 'Fremdbus',
            self::Sonstiges => 'Sonstiges',
        };
    }

    /** Vorbelegung. Bei der Fähre gilt „für Gäste“; fährt nur der Bus mit, nimmt man den Haken heraus. */
    public function isTravelService(): bool
    {
        return in_array($this, [self::Faehre, self::Hotel, self::Verpflegung, self::Eintritt, self::Reiseleiter, self::Fremdbus], true);
    }

    /** @return list<array{value: string, label: string, travel_service: bool}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label(), 'travel_service' => $c->isTravelService()], self::cases());
    }
}
