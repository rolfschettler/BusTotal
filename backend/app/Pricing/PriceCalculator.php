<?php

namespace App\Pricing;

/**
 * Preisfindung: macht aus den Kennzahlen einer Tour Belegpositionen. Austauschbar: Das endgültige Kalkulationsschema
 * (steht noch aus, siehe Fahrplan Phase 0) wird eine neue Klasse, die im AppServiceProvider eingetragen wird.
 */
interface PriceCalculator
{
    /**
     * Positionen im Format des DocumentService (title, description, quantity, unit, unit_price, tax_rate, tax_note).
     * Leer, wenn nichts berechnet werden kann.
     *
     * @return list<array<string, string|null>>
     */
    public function quote(TourFigures $tour): array;
}
