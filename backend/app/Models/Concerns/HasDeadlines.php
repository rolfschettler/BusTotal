<?php

namespace App\Models\Concerns;

/**
 * Fristen an einem Modell (Fahrzeug: HU, SP …; Fahrer: Führerschein, Fahrerkarte …).
 *
 * Das Modell legt eine Konstante DEADLINES fest (Spalte => Bezeichnung, Spaltennamen enden auf _due_date,
 * Typ date:Y-m-d) und fügt 'deadlines' und 'next_deadline' zu $appends hinzu.
 * Eine Frist ist "overdue" (Datum vor heute), "soon" (heute bis WARN_DAYS Tage) oder "ok".
 */
trait HasDeadlines
{
    /** Bis zu wie vielen Tagen vor Ablauf eine Frist als "bald fällig" gilt. */
    public const WARN_DAYS = 30;

    /** Alle gesetzten Fristen mit Zustand. `days` ist negativ, wenn die Frist abgelaufen ist. */
    public function getDeadlinesAttribute(): array
    {
        $today = now()->startOfDay();
        $out = [];
        foreach (static::DEADLINES as $column => $label) {
            $date = $this->{$column};
            if ($date === null) {
                continue;
            }
            $days = (int) $today->diffInDays($date->copy()->startOfDay(), false);
            $out[] = [
                'kind' => str_replace('_due_date', '', $column),
                'label' => $label,
                'date' => $date->format('Y-m-d'),
                'days' => $days,
                'status' => $days < 0 ? 'overdue' : ($days <= self::WARN_DAYS ? 'soon' : 'ok'),
            ];
        }

        return $out;
    }

    /** Die früheste Frist, oder null. */
    public function getNextDeadlineAttribute(): ?array
    {
        $all = $this->deadlines;
        usort($all, fn ($a, $b) => $a['days'] <=> $b['days']);

        return $all[0] ?? null;
    }
}
