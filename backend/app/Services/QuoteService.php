<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\GermanState;
use App\Enums\TripKind;
use App\Models\CompanySetting;
use App\Models\Document;
use App\Models\PricingSetting;
use App\Models\Vehicle;
use App\Pricing\GermanHolidays;
use App\Pricing\PriceCalculator;
use App\Pricing\TourDay;
use App\Pricing\TourFigures;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Verbindet Streckenplanung, Preisfindung und Belege: aus den Kennzahlen einer geplanten Tour entsteht ein Angebot als Entwurf.
 * Die Module kennen sich nicht gegenseitig: Dieser Dienst reicht nur Zahlen und Positionen weiter.
 */
class QuoteService
{
    public function __construct(
        private readonly PriceCalculator $prices,
        private readonly DocumentService $documents,
    ) {}

    /**
     * @param  array{loaded_km: float, empty_km: float, drive_s: int, days: int, surcharge_percent?: float, label?: ?string, start_at?: ?string, end_at?: ?string, state?: ?string, vehicle_id?: ?string, trip_kind?: ?string}  $tour
     */
    public function draftFromTour(array $tour, ?string $customerId = null): Document
    {
        // Der Typ des Fahrzeugs bestimmt die Preise; nur der Typ geht weiter, nicht das Fahrzeug selbst.
        $vehicleType = ! empty($tour['vehicle_id']) ? Vehicle::withTrashed()->find($tour['vehicle_id'])?->type : null;

        if (! PricingSetting::current()->hasAnyPrice($vehicleType)) {
            throw ValidationException::withMessages(['pricing' => ['In den Einstellungen sind noch keine Preise hinterlegt. Bitte zuerst unter „Preise“ eintragen.']]);
        }

        $lines = $this->prices->quote(new TourFigures(
            loadedKm: (float) $tour['loaded_km'],
            emptyKm: (float) $tour['empty_km'],
            driveSeconds: (int) $tour['drive_s'],
            days: max(1, (int) $tour['days']),
            surchargePercent: (float) ($tour['surcharge_percent'] ?? 0),
            vehicleType: $vehicleType,
            calendarDays: $this->calendarDays($tour),
            // Ohne Angabe: Gelegenheitsverkehr (Regelsatz). Linienverkehr und Schülerfahrt müssen gewählt werden.
            tripKind: TripKind::tryFrom((string) ($tour['trip_kind'] ?? '')) ?? TripKind::Gelegenheit,
        ));

        if ($lines === []) {
            throw ValidationException::withMessages(['pricing' => ['Mit den hinterlegten Preisen ergibt diese Tour keine Position.']]);
        }

        $label = trim((string) ($tour['label'] ?? ''));

        return $this->documents->createDraft(DocumentType::Angebot, [
            'customer_id' => $customerId,
            'subject' => $label !== '' ? mb_substr('Busfahrt '.$label, 0, 255) : 'Busfahrt',
            'service_date_from' => ! empty($tour['start_at']) ? $this->localDay($tour['start_at'])->toDateString() : null,
        ], $lines);
    }

    /** Der Kalendertag eines Zeitpunkts in deutscher Zeit (der Browser sendet UTC: 00:30 Uhr hier ist dort noch der Vortag). */
    private function localDay(string $at): Carbon
    {
        return Carbon::parse($at)->setTimezone('Europe/Berlin')->startOfDay();
    }

    /**
     * Die Kalendertage der Tour vom Start- bis zum Endtag mit Samstag, Sonntag und Feiertag. Ohne Starttermin gibt es keine Tage.
     * Das Bundesland der Feiertage kommt aus der Tour, sonst vom Betriebssitz; ohne beides zählen nur Samstag und Sonntag.
     *
     * @return list<TourDay>
     */
    private function calendarDays(array $tour): array
    {
        if (empty($tour['start_at'])) {
            return [];
        }

        $first = $this->localDay($tour['start_at']);
        // Ohne Endzeit rechnen wir mit den Einsatztagen; ein Ende vor dem Start zählt nicht.
        $last = ! empty($tour['end_at']) ? $this->localDay($tour['end_at']) : $first->copy()->addDays(max(1, (int) $tour['days']) - 1);
        if ($last->lt($first)) {
            $last = $first->copy();
        }

        $state = GermanState::tryFrom((string) ($tour['state'] ?? '')) ?? GermanState::tryFrom((string) CompanySetting::current()->state);

        $days = [];
        for ($d = $first->copy(); $d->lte($last) && count($days) < 62; $d->addDay()) {
            $days[] = new TourDay($d->toDateString(), $d->isSaturday(), $d->isSunday(), $state ? GermanHolidays::name($d, $state) : null);
        }

        return $days;
    }
}
