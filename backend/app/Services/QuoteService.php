<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\PricingSetting;
use App\Pricing\PriceCalculator;
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
     * @param  array{loaded_km: float, empty_km: float, drive_s: int, days: int, surcharge_percent?: float, label?: ?string, start_at?: ?string}  $tour
     */
    public function draftFromTour(array $tour, ?string $customerId = null): Document
    {
        if (! PricingSetting::current()->hasAnyPrice()) {
            throw ValidationException::withMessages(['pricing' => ['In den Einstellungen sind noch keine Preise hinterlegt. Bitte zuerst unter „Preise“ eintragen.']]);
        }

        $lines = $this->prices->quote(new TourFigures(
            loadedKm: (float) $tour['loaded_km'],
            emptyKm: (float) $tour['empty_km'],
            driveSeconds: (int) $tour['drive_s'],
            days: max(1, (int) $tour['days']),
            surchargePercent: (float) ($tour['surcharge_percent'] ?? 0),
        ));

        if ($lines === []) {
            throw ValidationException::withMessages(['pricing' => ['Mit den hinterlegten Preisen ergibt diese Tour keine Position.']]);
        }

        $label = trim((string) ($tour['label'] ?? ''));

        return $this->documents->createDraft(DocumentType::Angebot, [
            'customer_id' => $customerId,
            'subject' => $label !== '' ? mb_substr('Busfahrt '.$label, 0, 255) : 'Busfahrt',
            'service_date_from' => ! empty($tour['start_at']) ? Carbon::parse($tour['start_at'])->toDateString() : null,
        ], $lines);
    }
}
