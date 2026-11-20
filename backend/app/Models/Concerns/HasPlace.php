<?php

namespace App\Models\Concerns;

use App\Models\Place;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Für jeden Datensatz mit Adresse, der in einer Geo-Funktion (Streckenplanung, Entfernungen, Umkreissuche) vorkommen kann:
 * Die Koordinaten liegen im verknüpften Ort (`place_id` → places), nie in eigenen lat/lon-Spalten und nie per Direktanfrage an
 * den Adressdienst. `App\Services\PlaceResolver::assign()` setzt den Ort und fragt den Dienst nur, wenn die Adresse neu ist.
 *
 * Das Modell braucht die Spalte `place_id` (uuid, leer erlaubt, Verweis auf places), nennt in `placeFields()` die Felder, aus denen
 * die Adresse besteht, und baut in `placeQuery()` den Suchtext daraus. `place_id` gehört nicht in `$fillable`.
 */
trait HasPlace
{
    /** Die Felder, aus denen die Adresse besteht: Ändert sich eines, gehört ein anderer Ort dazu. */
    abstract public function placeFields(): array;

    /** Der Suchtext für den Ort; null oder leer, wenn keine Adresse da ist. */
    abstract public function placeQuery(): ?string;

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** Fehlt der Ort, oder wurde die Adresse geändert (bei einem neuen Datensatz: immer, sobald eine Adresse da ist)? */
    public function needsPlace(): bool
    {
        return $this->isDirty($this->placeFields()) || ($this->place_id === null && trim((string) $this->placeQuery()) !== '');
    }
}
