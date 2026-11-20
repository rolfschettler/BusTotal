<?php

namespace App\Enums;

/** Belegarten der Belegkette Angebot → Auftragsbestätigung → (Anzahlung →) Rechnung; Gutschrift = Storno. */
enum DocumentType: string
{
    case Angebot = 'angebot';
    case Auftragsbestaetigung = 'auftragsbestaetigung';
    case Anzahlung = 'anzahlung';
    case Rechnung = 'rechnung';
    case Gutschrift = 'gutschrift';

    public function label(): string
    {
        return match ($this) {
            self::Angebot => 'Angebot',
            self::Auftragsbestaetigung => 'Auftragsbestätigung',
            self::Anzahlung => 'Anzahlungsrechnung',
            self::Rechnung => 'Rechnung',
            self::Gutschrift => 'Gutschrift / Storno',
        };
    }

    /**
     * Rechnungen im steuerlichen Sinn: Pflichtangaben nach § 14 UStG, Aufbewahrung 8 Jahre, GoBD-streng.
     * Angebot und Auftragsbestätigung sind Geschäftsbriefe (6 Jahre).
     */
    public function isInvoice(): bool
    {
        return in_array($this, [self::Anzahlung, self::Rechnung, self::Gutschrift], true);
    }

    /**
     * Nummernkreis (Typ in number_ranges). Anzahlungs- und normale Rechnungen teilen sich EINEN Kreis,
     * damit die Rechnungsnummern fortlaufend bleiben (§ 14 Abs. 4 Nr. 4 UStG).
     */
    public function numberRange(): string
    {
        return match ($this) {
            self::Anzahlung => self::Rechnung->value,
            default => $this->value,
        };
    }

    /** Welche Belegarten dürfen aus diesem Beleg abgeleitet werden. */
    public function convertibleTo(): array
    {
        return match ($this) {
            self::Angebot => [self::Auftragsbestaetigung, self::Anzahlung, self::Rechnung],
            self::Auftragsbestaetigung => [self::Anzahlung, self::Rechnung],
            default => [],
        };
    }
}
