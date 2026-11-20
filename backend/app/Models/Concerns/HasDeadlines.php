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
            $warn = static::warnDaysOf($column);
            $out[] = [
                'kind' => str_replace('_due_date', '', $column),
                'label' => $label,
                'date' => $date->format('Y-m-d'),
                'days' => $days,
                'warn_days' => $warn,
                'status' => $days < 0 ? 'overdue' : ($days <= $warn ? 'soon' : 'ok'),
            ];
        }

        return $out;
    }

    /**
     * Ab wie vielen Tagen vor Ablauf eine Frist als "bald fällig" gilt. Ein Modell kann je Spalte einen kürzeren Zeitraum
     * festlegen (Konstante WARN_DAYS_OVERRIDE): Eine Frist von 28 Tagen wäre sonst gleich nach dem Auslesen "bald fällig".
     */
    public static function warnDaysOf(string $column): int
    {
        $overrides = defined(static::class.'::WARN_DAYS_OVERRIDE') ? static::WARN_DAYS_OVERRIDE : [];

        return $overrides[$column] ?? self::WARN_DAYS;
    }

    /** Die früheste Frist, oder null. */
    public function getNextDeadlineAttribute(): ?array
    {
        $all = $this->deadlines;
        usort($all, fn ($a, $b) => $a['days'] <=> $b['days']);

        return $all[0] ?? null;
    }
}
