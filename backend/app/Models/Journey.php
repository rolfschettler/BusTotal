<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Reisevorhaben: schlanker, optionaler Container (Kunde, Titel, Zeitraum) mit Abschnitten. Er wird von Hand geplant („Neue Reise
 * planen“, `manual`) oder entsteht mit dem Kurzweg „Angebot erstellen“ aus der Streckenplanung. Summen, Marge und Stand werden aus
 * den Belegen abgeleitet, nie hier gespeichert.
 *
 * Abgesagt wird eine Reise von Hand (JourneyCancellationService). Abgeschlossen ist sie von selbst; die Regel steht nur hier
 * (completionRule).
 */
class Journey extends Model
{
    use Auditable, HasUuids;

    protected $fillable = ['title', 'customer_id', 'manual', 'date_from', 'date_to'];

    protected function casts(): array
    {
        return ['date_from' => 'date:Y-m-d', 'date_to' => 'date:Y-m-d', 'cancelled_on' => 'date:Y-m-d', 'manual' => 'boolean'];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_on !== null;
    }

    public function scopeCancelled(Builder $query): void
    {
        $query->whereNotNull('journeys.cancelled_on');
    }

    /** Alles, was weder abgesagt noch abgeschlossen ist. */
    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('journeys.cancelled_on')->whereNot(fn (Builder $w) => self::completionRule($w));
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->whereNull('journeys.cancelled_on')->where(fn (Builder $w) => self::completionRule($w));
    }

    /**
     * Abgeschlossen: Die Reise liegt vor heute (ohne Reisedatum nie), es gibt eine gültige festgeschriebene Rechnung, und keine
     * Rechnung der Reise ist noch offen (Entwurf oder nicht voll bezahlt). Anzahlungen zählen nicht. Alle Bedingungen sind auch bei
     * leeren Feldern eindeutig wahr oder falsch, damit die Umkehrung (scopeRunning) keine Reise verliert.
     */
    private static function completionRule(Builder $query): void
    {
        $today = Carbon::today('Europe/Berlin')->toDateString();
        $invoices = fn ($q) => $q->selectRaw('1')->from('documents as d')->whereColumn('d.journey_id', 'journeys.id')
            ->where('d.type', DocumentType::Rechnung->value)->whereNull('d.cancelled_by_id');

        $query->where(fn ($date) => $date
            ->where(fn ($a) => $a->whereNotNull('journeys.date_to')->where('journeys.date_to', '<', $today))
            ->orWhere(fn ($b) => $b->whereNull('journeys.date_to')->whereNotNull('journeys.date_from')->where('journeys.date_from', '<', $today)))
            ->whereExists(fn ($q) => $invoices($q)->where('d.status', DocumentStatus::Festgeschrieben->value))
            // Bezahlt wie in PaymentService::paidTotals: Eingänge minus Rückzahlungen, ohne stornierte Buchungen
            ->whereNotExists(fn ($q) => $invoices($q)->where(fn ($open) => $open
                ->where('d.status', DocumentStatus::Entwurf->value)
                ->orWhereRaw("ROUND(d.payable_total - COALESCE((SELECT SUM(CASE WHEN p.kind = 'eingang' THEN p.amount ELSE -p.amount END)
                    FROM document_payments p WHERE p.document_id = d.id AND p.cancelled_at IS NULL), 0), 2) > 0")));
    }

    public function sections(): HasMany
    {
        return $this->hasMany(JourneySection::class)->orderBy('position');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
