<?php

namespace Tests\Concerns;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;

/**
 * Rechnung als Entwurf zu einem Beleg, ohne den Weg über /convert. Seit der Konzeptentscheidung (2026-11-24) entsteht die
 * Rechnung nicht mehr aus Angebot oder Auftragsbestätigung, sondern aus den Fahrtaufträgen (Etappe 4). Bis dahin bildet
 * dieser Baustein den Stand nach, den die Rechnungstests brauchen: gleicher Kunde, gleiche Positionen, source_id auf den Beleg.
 */
trait CreatesInvoiceDrafts
{
    /** @return array<string, mixed> der Rechnungsentwurf wie GET /api/documents/{id} */
    protected function invoiceDraftFrom(string $sourceId): array
    {
        $source = Document::with('lines')->findOrFail($sourceId);

        $lines = $source->lines->map(fn ($l) => [
            'title' => $l->title, 'description' => $l->description, 'quantity' => (string) $l->quantity,
            'unit' => $l->unit, 'trip_kind' => $l->trip_kind?->value, 'unit_price' => (string) $l->unit_price,
            'tax_rate' => (string) $l->tax_rate, 'tax_note' => $l->tax_note,
            'journey_section_id' => $l->journey_section_id, 'vehicle_type' => $l->vehicle_type,
        ])->all();

        $data = [
            'customer_id' => $source->customer_id, 'subject' => $source->subject, 'intro_text' => $source->intro_text,
            'footer_text' => $source->footer_text, 'service_date_from' => $source->service_date_from,
            'service_date_to' => $source->service_date_to,
        ];
        $service = app(DocumentService::class);
        $draft = $service->createDraft(DocumentType::Rechnung, $data, [], $source->journey_id);
        $draft->forceFill(['source_id' => $source->id])->save();
        // Erst jetzt die Positionen: Die Abzüge der Anzahlungen werden über source_id bestimmt.
        $service->updateDraft($draft, $data, $lines);

        return $this->actingAs(User::factory()->create(['role' => Role::Buchhaltung]), 'sanctum')
            ->getJson("/api/documents/{$draft->id}")->assertOk()->json();
    }
}
