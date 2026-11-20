<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentPdf;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/** Archiv-PDF (GoBD): entsteht einmal beim Festschreiben, wird unverändert ausgeliefert, nie neu erzeugt. */
class DocumentArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457', 'city' => 'Hamburg',
            'tax_number' => '12/345/67890', 'payment_term_days' => 14, 'brand_color' => '#1d4e89',
        ]);
        $this->customer = Customer::factory()->create([
            'company_name' => 'Reisebüro Sonnenschein', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München',
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function draft(string $type = 'rechnung'): array
    {
        return $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => $type, 'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10',
            'subject' => 'Busfahrt', 'lines' => [['title' => 'Fahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '1000', 'tax_rate' => 19]],
        ])->assertCreated()->json();
    }

    private function finalize(string $id)
    {
        return $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize");
    }

    private function archivePath(string $id): string
    {
        return 'archive/documents/'.$id.'.pdf';
    }

    private function pdf(string $id)
    {
        return $this->as(Role::Buchhaltung)->get("/api/documents/{$id}/pdf");
    }

    private function number(string $number): int
    {
        return (int) preg_replace('/\D+/', '', substr($number, -6));
    }

    public function test_festschreiben_legt_pdf_und_pruefsumme_ab(): void
    {
        $doc = $this->finalize($this->draft()['id'])->assertOk()->json();

        $bytes = Storage::disk('local')->get($this->archivePath($doc['id']));
        $this->assertStringStartsWith('%PDF-', $bytes);

        $row = Document::findOrFail($doc['id']);
        $this->assertSame(hash('sha256', $bytes), $row->archive_sha256);
        $this->assertNotNull($row->archived_at);
        // Pfad und Prüfsumme gehören nicht in die Schnittstelle.
        $this->assertArrayNotHasKey('archive_path', $doc);
        $this->assertArrayNotHasKey('archive_sha256', $doc);
    }

    public function test_abruf_liefert_die_abgelegte_datei_byte_gleich_und_erzeugt_nicht_neu(): void
    {
        $doc = $this->finalize($this->draft()['id'])->assertOk()->json();
        $stored = Storage::disk('local')->get($this->archivePath($doc['id']));

        $this->mock(DocumentPdf::class, fn ($m) => $m->shouldNotReceive('render'));

        $this->assertSame($stored, $this->pdf($doc['id'])->assertOk()->getContent());
        $this->assertSame($stored, $this->pdf($doc['id'])->assertOk()->getContent());
    }

    public function test_pdf_bleibt_nach_aenderung_von_kunde_firma_und_logo_byte_gleich(): void
    {
        $this->as(Role::Admin)->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->image('a.png', 40, 40),
        ])->assertOk();
        $doc = $this->finalize($this->draft()['id'])->assertOk()->json();
        $before = $this->pdf($doc['id'])->assertOk()->getContent();

        $this->customer->update(['company_name' => 'Umbenannt AG', 'city' => 'Berlin']);
        CompanySetting::current()->update(['name' => 'Neue Firma GmbH', 'brand_color' => '#aa0000', 'footer_text' => 'Neu']);
        $this->as(Role::Admin)->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->image('b.png', 80, 20),
        ])->assertOk();
        $this->customer->delete();

        $this->assertSame($before, $this->pdf($doc['id'])->assertOk()->getContent());
    }

    public function test_anonymisieren_beruehrt_das_archiv_pdf_nicht(): void
    {
        $doc = $this->finalize($this->draft()['id'])->assertOk()->json();
        $before = Storage::disk('local')->get($this->archivePath($doc['id']));

        $this->as(Role::Buchhaltung)->postJson("/api/customers/{$this->customer->id}/anonymize")->assertOk();

        $this->assertSame($before, $this->pdf($doc['id'])->assertOk()->getContent());
    }

    public function test_veraenderte_datei_wird_erkannt_und_nicht_ausgeliefert(): void
    {
        $doc = $this->finalize($this->draft()['id'])->assertOk()->json();
        Storage::disk('local')->put($this->archivePath($doc['id']), '%PDF-1.4 manipuliert');

        $this->pdf($doc['id'])->assertStatus(500)->assertJsonPath('message', fn ($m) => str_contains($m, 'Prüfsumme'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'archive_check_failed', 'auditable_id' => $doc['id']]);
        // Es wurde nicht stillschweigend neu erzeugt.
        $this->assertSame('%PDF-1.4 manipuliert', Storage::disk('local')->get($this->archivePath($doc['id'])));
    }

    public function test_fehlende_datei_wird_gemeldet(): void
    {
        $doc = $this->finalize($this->draft()['id'])->assertOk()->json();
        Storage::disk('local')->delete($this->archivePath($doc['id']));

        $this->pdf($doc['id'])->assertStatus(500)->assertJsonPath('message', fn ($m) => str_contains($m, 'fehlt'));
        $this->assertFalse(Storage::disk('local')->exists($this->archivePath($doc['id'])));
    }

    public function test_scheitert_die_erzeugung_bleibt_der_entwurf_und_keine_nummer_wird_verbraucht(): void
    {
        $first = $this->finalize($this->draft()['id'])->assertOk()->json();
        $second = $this->draft();

        $this->mock(DocumentPdf::class, fn ($m) => $m->shouldReceive('render')->andThrow(new \RuntimeException('dompdf kaputt')));
        $this->finalize($second['id'])->assertUnprocessable();

        $row = Document::findOrFail($second['id']);
        $this->assertTrue($row->isDraft());
        $this->assertNull($row->number);
        $this->assertNull($row->archive_path);
        $this->assertFalse(Storage::disk('local')->exists($this->archivePath($second['id'])));

        // Nach der Reparatur geht es weiter, und die Nummer folgt lückenlos.
        $this->app->forgetInstance(DocumentPdf::class);
        $this->app->offsetUnset(DocumentPdf::class);
        $retry = $this->finalize($second['id'])->assertOk()->json();
        $this->assertSame($this->number($first['number']) + 1, $this->number($retry['number']));
    }

    public function test_entwurf_wird_weiter_frisch_erzeugt_und_nicht_abgelegt(): void
    {
        $doc = $this->draft();

        $this->pdf($doc['id'])->assertOk();

        $this->assertFalse(Storage::disk('local')->exists($this->archivePath($doc['id'])));
        $this->assertNull(Document::findOrFail($doc['id'])->archive_path);
    }

    public function test_storno_legt_auch_das_pdf_der_gutschrift_ab(): void
    {
        $rechnung = $this->finalize($this->draft()['id'])->assertOk()->json();

        $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$rechnung['id']}/cancel")->assertCreated()->json();

        $bytes = Storage::disk('local')->get($this->archivePath($storno['id']));
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(hash('sha256', $bytes), Document::findOrFail($storno['id'])->archive_sha256);
        $this->assertSame($bytes, $this->pdf($storno['id'])->assertOk()->getContent());
        // Das Archiv-PDF der Originalrechnung bleibt, wie es war.
        $this->assertTrue(Storage::disk('local')->exists($this->archivePath($rechnung['id'])));
    }

    public function test_alter_beleg_ohne_archiv_wird_einmal_nacherzeugt(): void
    {
        $doc = $this->finalize($this->draft()['id'])->assertOk()->json();
        Storage::disk('local')->delete($this->archivePath($doc['id']));
        DB::table('documents')->where('id', $doc['id'])->update(['archive_path' => null, 'archive_sha256' => null, 'archived_at' => null]);

        $first = $this->pdf($doc['id'])->assertOk()->getContent();
        $this->assertDatabaseHas('audit_logs', ['event' => 'archive_backfilled', 'auditable_id' => $doc['id']]);
        $this->assertNotNull(Document::findOrFail($doc['id'])->archived_at);

        // Beim zweiten Abruf wird nichts mehr erzeugt.
        $this->mock(DocumentPdf::class, fn ($m) => $m->shouldNotReceive('render'));
        $this->assertSame($first, $this->pdf($doc['id'])->assertOk()->getContent());
        $this->assertSame(1, AuditLog::where('event', 'archive_backfilled')->count());
    }

    public function test_archiv_felder_lassen_sich_nach_dem_festschreiben_nicht_mehr_aendern(): void
    {
        $doc = Document::findOrFail($this->finalize($this->draft()['id'])->assertOk()->json('id'));

        $this->expectException(LogicException::class);
        $doc->forceFill(['archive_sha256' => str_repeat('0', 64)])->save();
    }
}
