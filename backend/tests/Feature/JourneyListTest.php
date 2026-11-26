<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\PricingSetting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInvoiceDrafts;
use Tests\TestCase;

/** Reisen: Übersicht und Detail (nur lesen), Stand und Betrag aus den Belegen abgeleitet, Verwendung der Touren im Archiv. */
class JourneyListTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInvoiceDrafts;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake();
        $this->seed(SettingsSeeder::class);
        PricingSetting::current()->update(['price_per_km' => 2, 'empty_km_percent' => 100, 'tax_rate' => 19, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0, 'minimum_price' => 0]);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function quote(string $label, ?Customer $customer = null, ?string $tripId = null): array
    {
        $body = ['loaded_km' => 100, 'empty_km' => 0, 'drive_s' => 3600, 'days' => 1, 'label' => $label, 'vehicle_type' => 'doppeldecker', 'start_at' => '2027-05-10T06:00:00+02:00']
            + ($customer ? ['customer_id' => $customer->id] : []) + ($tripId ? ['trip_id' => $tripId] : []);

        return $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $body)->assertCreated()->json();
    }

    private function customer(string $name): Customer
    {
        return Customer::factory()->create(['company_name' => $name, 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
    }

    public function test_rechte(): void
    {
        $this->getJson('/api/journeys')->assertUnauthorized();
        $quote = $this->quote('München – Wien');

        $this->as(Role::Fahrer)->getJson('/api/journeys')->assertForbidden();
        foreach ([Role::Admin, Role::Disponent, Role::Buchhaltung] as $role) {
            $this->as($role)->getJson('/api/journeys')->assertOk();
            $this->as($role)->getJson('/api/journeys/'.$quote['journey_id'])->assertOk();
        }
        // Anlegen und Löschen: nur Admin und Disponent; eine Reise mit Belegen lässt sich nicht löschen
        $this->as(Role::Buchhaltung)->postJson('/api/journeys', ['title' => 'X'])->assertForbidden();
        $this->as(Role::Buchhaltung)->deleteJson('/api/journeys/'.$quote['journey_id'])->assertForbidden();
        $this->as(Role::Admin)->postJson('/api/journeys', ['title' => 'X'])->assertCreated();
        // Nur ein Entwurf dabei: Er verschwindet mit der Reise und ihren Strecken
        $this->as(Role::Admin)->deleteJson('/api/journeys/'.$quote['journey_id'])->assertOk();
        $this->assertDatabaseMissing('journeys', ['id' => $quote['journey_id']]);
        $this->assertDatabaseMissing('documents', ['id' => $quote['id']]);
        $this->assertDatabaseMissing('journey_sections', ['journey_id' => $quote['journey_id']]);
    }

    public function test_reise_mit_festgeschriebenem_beleg_laesst_sich_nicht_loeschen(): void
    {
        $quote = $this->quote('München – Wien');
        DB::table('documents')->where('id', $quote['id'])->update(['status' => 'festgeschrieben']);

        $this->as(Role::Admin)->deleteJson('/api/journeys/'.$quote['journey_id'])
            ->assertUnprocessable()->assertJsonValidationErrors('journey');
        $this->assertDatabaseHas('journeys', ['id' => $quote['journey_id']]);
        $this->assertDatabaseHas('documents', ['id' => $quote['id']]);
    }

    public function test_leere_reise_loeschen(): void
    {
        $id = $this->as(Role::Disponent)->postJson('/api/journeys', ['title' => 'Leer'])->assertCreated()->json('id');
        $this->as(Role::Disponent)->deleteJson("/api/journeys/$id")->assertOk();
        $this->assertDatabaseMissing('journeys', ['id' => $id]);
    }

    public function test_reise_umbenennen(): void
    {
        $quote = $this->quote('München – Wien', $this->customer('Reisebüro Sonnenschein'));
        $id = $quote['journey_id'];

        $this->as(Role::Disponent)->putJson("/api/journeys/$id", ['title' => '  Klassenfahrt Realschule Mai  '])->assertOk()
            ->assertJsonPath('title', 'Klassenfahrt Realschule Mai')
            ->assertJsonPath('sections.0.title', 'München – Wien')   // die Strecke behält den Namen der Tour
            ->assertJsonPath('documents.0.id', $quote['id']);

        // überall derselbe neue Name: Liste, Suche, Beleg
        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.title', 'Klassenfahrt Realschule Mai');
        $this->as(Role::Buchhaltung)->getJson('/api/journeys?q=klassen')->assertJsonCount(1, 'data');
        $this->as(Role::Buchhaltung)->getJson('/api/journeys?q=wien')->assertJsonCount(0, 'data');
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$quote['id']}")->assertJsonPath('journey.title', 'Klassenfahrt Realschule Mai');
    }

    public function test_umbenennen_prueft_den_namen_und_die_rechte(): void
    {
        $this->putJson('/api/journeys/3f0e0f6e-7c8b-4c1a-9a53-0d2b1f5b7a11', ['title' => 'X'])->assertUnauthorized();
        $quote = $this->quote('München – Wien');
        $id = $quote['journey_id'];

        $this->as(Role::Fahrer)->putJson("/api/journeys/$id", ['title' => 'X'])->assertForbidden();
        $this->as(Role::Admin)->putJson("/api/journeys/$id", ['title' => ''])->assertUnprocessable()->assertJsonValidationErrors(['title']);
        $this->as(Role::Admin)->putJson("/api/journeys/$id", ['title' => '   '])->assertUnprocessable()->assertJsonValidationErrors(['title']);
        $this->as(Role::Admin)->putJson("/api/journeys/$id", ['title' => str_repeat('x', 151)])->assertUnprocessable()->assertJsonValidationErrors(['title']);
        $this->as(Role::Admin)->putJson("/api/journeys/$id", [])->assertUnprocessable();
        // andere Felder lassen sich über diese Schnittstelle nicht ändern
        $this->as(Role::Admin)->putJson("/api/journeys/$id", ['title' => 'Neu', 'customer_id' => null, 'date_from' => '2030-01-01'])->assertOk();

        $journey = \App\Models\Journey::findOrFail($id);
        $this->assertSame('Neu', $journey->title);
        $this->assertSame('2027-05-10', $journey->date_from?->toDateString());
        $this->as(Role::Admin)->putJson('/api/journeys/3f0e0f6e-7c8b-4c1a-9a53-0d2b1f5b7a11', ['title' => 'X'])->assertNotFound();
    }

    public function test_umbenannte_reise_aendert_festgeschriebene_belege_nicht(): void
    {
        $customer = $this->customer('Reisebüro');
        $quote = $this->quote('München – Wien', $customer);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();
        $before = \App\Models\Document::findOrFail($quote['id'])->only(['subject', 'tour_snapshot', 'net_total']);

        $this->as(Role::Disponent)->putJson('/api/journeys/'.$quote['journey_id'], ['title' => 'Ganz anders'])->assertOk();

        $this->assertEquals($before, \App\Models\Document::findOrFail($quote['id'])->only(['subject', 'tour_snapshot', 'net_total']));
    }

    public function test_liste_neueste_zuerst_mit_kunde_stand_und_betrag(): void
    {
        $this->quote('Ulm – Bonn');
        $customer = $this->customer('Reisebüro Sonnenschein');
        $second = $this->quote('München – Wien', $customer);

        $res = $this->as(Role::Disponent)->getJson('/api/journeys')->assertOk()->assertJsonCount(2, 'data');

        $res->assertJsonPath('data.0.title', 'München – Wien')
            ->assertJsonPath('data.0.customer.company_name', 'Reisebüro Sonnenschein')
            ->assertJsonPath('data.0.stage', 'angebot')
            ->assertJsonPath('data.0.stage_document_id', $second['id'])
            ->assertJsonPath('data.0.amount', '238.00')   // 100 km × 2 € + 19 %
            ->assertJsonPath('data.0.document_count', 1)
            ->assertJsonPath('data.0.sections_count', 1)
            ->assertJsonPath('data.1.title', 'Ulm – Bonn')
            ->assertJsonPath('data.1.customer', null);
    }

    public function test_stand_folgt_dem_weitesten_gueltigen_beleg_und_ignoriert_storno_und_anzahlung(): void
    {
        $customer = $this->customer('Reisebüro');
        $quote = $this->quote('München – Wien', $customer);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/finalize")->assertOk();

        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'auftragsbestaetigung')->assertJsonPath('data.0.document_count', 2);

        $advance = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/convert", ['type' => 'anzahlung'])->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$advance['id']}/finalize")->assertOk();
        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'auftragsbestaetigung');   // Anzahlung zählt nicht als Stand

        $invoice = $this->invoiceDraftFrom($order['id']);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/finalize")->assertOk();
        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'rechnung')->assertJsonPath('data.0.stage_document_id', $invoice['id']);

        // Rechnung storniert: Der Stand fällt auf die Auftragsbestätigung zurück.
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/cancel")->assertCreated();
        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'auftragsbestaetigung')->assertJsonPath('data.0.document_count', 5);
    }

    /** Reise mit festgeschriebener Rechnung (238,00 €) und gesetztem Reisezeitraum. */
    private function invoiced(string $label, ?string $dateFrom = '2020-05-10', ?string $dateTo = '2020-05-10'): array
    {
        $quote = $this->quote($label, $this->customer('Reisebüro '.$label));
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();
        $invoice = $this->invoiceDraftFrom($quote['id']);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/finalize")->assertOk();
        DB::table('journeys')->where('id', $quote['journey_id'])->update(['date_from' => $dateFrom, 'date_to' => $dateTo]);

        return ['journey' => $quote['journey_id'], 'quote' => $quote['id'], 'invoice' => $invoice['id']];
    }

    private function pay(string $documentId, string $amount, string $kind = 'eingang'): string
    {
        return $this->as(Role::Buchhaltung)->postJson("/api/documents/$documentId/payments", ['kind' => $kind, 'amount' => $amount, 'paid_on' => now()->toDateString()])
            ->assertCreated()->json('id') ?? '';
    }

    /** @return list<string> */
    private function titles(string $query = ''): array
    {
        return array_column($this->as(Role::Disponent)->getJson('/api/journeys'.$query)->assertOk()->json('data'), 'title');
    }

    public function test_abgeschlossen_erst_wenn_vorbei_und_die_rechnung_bezahlt_ist(): void
    {
        $ids = $this->invoiced('Wien');
        $this->assertSame(['Wien'], $this->titles());
        $this->pay($ids['invoice'], '100.00');
        $this->assertSame(['Wien'], $this->titles());                              // nur teilweise bezahlt
        $this->assertSame([], $this->titles('?view=abgeschlossen'));

        $this->pay($ids['invoice'], '138.00');
        $this->assertSame([], $this->titles());
        $this->assertSame(['Wien'], $this->titles('?view=abgeschlossen'));
        $this->assertSame(['Wien'], $this->titles('?view=alle'));
        $this->as(Role::Disponent)->getJson('/api/journeys?view=alle')->assertJsonPath('data.0.state', 'abgeschlossen');
        $this->as(Role::Disponent)->getJson('/api/journeys/'.$ids['journey'])->assertJsonPath('state', 'abgeschlossen');
        // abgeschlossen: nicht mehr absagbar
        $this->as(Role::Disponent)->postJson("/api/journeys/{$ids['journey']}/cancel", ['date' => now()->toDateString(), 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['journey']);

        // Rückzahlung: wieder offen
        $this->pay($ids['invoice'], '10.00', 'rueckzahlung');
        $this->assertSame(['Wien'], $this->titles());
    }

    public function test_ohne_vergangenes_reisedatum_nie_abgeschlossen(): void
    {
        $today = now('Europe/Berlin')->toDateString();
        foreach ([['Heute', $today, $today], ['Ohne', null, null], ['Zukunft', '2020-01-01', '2099-01-01'], ['NurBeginn', '2020-01-01', null]] as [$label, $from, $to]) {
            $this->pay($this->invoiced($label, $from, $to)['invoice'], '238.00');
        }

        $this->assertSame(['NurBeginn'], $this->titles('?view=abgeschlossen'));
        $this->assertEqualsCanonicalizing(['Heute', 'Ohne', 'Zukunft'], $this->titles());
    }

    public function test_ohne_gueltige_rechnung_oder_mit_rechnungsentwurf_laeuft_die_reise_weiter(): void
    {
        $quote = $this->quote('NurAngebot');
        DB::table('journeys')->where('id', $quote['journey_id'])->update(['date_from' => '2020-01-01', 'date_to' => '2020-01-01']);

        $storno = $this->invoiced('Storniert');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$storno['invoice']}/cancel")->assertCreated();

        $draft = $this->invoiced('Entwurf');
        $this->pay($draft['invoice'], '238.00');
        $this->invoiceDraftFrom($draft['quote']);

        $this->assertSame([], $this->titles('?view=abgeschlossen'));
        $this->assertEqualsCanonicalizing(['NurAngebot', 'Storniert', 'Entwurf'], $this->titles());
    }

    public function test_ansicht_abgesagt_und_treffer_ausserhalb_der_ansicht(): void
    {
        $this->quote('Wien eins');
        $gone = $this->quote('Wien zwei');
        $this->as(Role::Disponent)->postJson("/api/journeys/{$gone['journey_id']}/cancel", ['date' => now('Europe/Berlin')->toDateString(), 'reason' => 'x'])->assertOk();

        $this->assertSame(['Wien eins'], $this->titles());
        $this->assertSame(['Wien zwei'], $this->titles('?view=abgesagt'));
        $this->as(Role::Disponent)->getJson('/api/journeys?q=wien')->assertJsonPath('total', 1)->assertJsonPath('other_matches', 1);
        $this->as(Role::Disponent)->getJson('/api/journeys?q=wien&view=alle')->assertJsonPath('total', 2)->assertJsonPath('other_matches', 0);
        $this->as(Role::Disponent)->getJson('/api/journeys')->assertJsonPath('other_matches', 0);
        $this->as(Role::Disponent)->getJson('/api/journeys?view=unsinn')->assertUnprocessable()->assertJsonValidationErrors(['view']);
    }

    public function test_suche_trifft_titel_und_kunde_am_wortanfang(): void
    {
        $this->quote('München – Wien', $this->customer('Reisebüro Sonnenschein'));
        $this->quote('Ulm – Bonn', $this->customer('Verein Wanderlust'));

        $this->as(Role::Disponent)->getJson('/api/journeys?q=wien')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'München – Wien');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=sonnen')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'München – Wien');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=bonn')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Ulm – Bonn');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=ünchen')->assertJsonCount(0, 'data');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=100%25')->assertJsonCount(0, 'data');
    }

    public function test_detail_mit_strecken_fahrzeugen_tour_und_belegen(): void
    {
        $tripId = $this->as(Role::Disponent)->postJson('/api/trips', [
            'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => 400, 'empty_km' => 0, 'drive_s' => 18000, 'elapsed_s' => 30000, 'days' => 2,
            'stops' => [['label' => 'München', 'lat' => 48.1, 'lon' => 11.5], ['label' => 'Wien', 'lat' => 48.2, 'lon' => 16.3]],
        ])->assertCreated()->json('id');
        $customer = $this->customer('Reisebüro Sonnenschein');
        $quote = $this->quote('München – Wien', $customer, $tripId);

        $this->as(Role::Buchhaltung)->getJson('/api/journeys/'.$quote['journey_id'])->assertOk()
            ->assertJsonPath('title', 'München – Wien')
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('sections.0.kind_label', 'Hinfahrt')
            ->assertJsonPath('sections.0.vehicles.0.label', 'Doppeldeckerbus')
            ->assertJsonPath('sections.0.trip.id', $tripId)
            ->assertJsonPath('sections.0.trip.from', 'München')
            ->assertJsonPath('sections.0.trip.to', 'Wien')
            ->assertJsonPath('documents.0.id', $quote['id'])
            ->assertJsonPath('documents.0.gross_total', '238.00')
            ->assertJsonPath('stage', 'angebot')
            ->assertJsonPath('amount', '238.00');

        $this->as(Role::Disponent)->getJson('/api/journeys/3f0e0f6e-7c8b-4c1a-9a53-0d2b1f5b7a11')->assertNotFound();
    }

    public function test_detail_zeigt_den_festgehaltenen_stand_nicht_die_geaenderte_tour(): void
    {
        $tripBody = fn (float $km, string $to) => [
            'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => $km, 'empty_km' => 0, 'drive_s' => 18000, 'elapsed_s' => 30000, 'days' => 1,
            'stops' => [['label' => 'München', 'lat' => 48.1, 'lon' => 11.5], ['label' => $to, 'lat' => 48.2, 'lon' => 16.3]],
        ];
        $tripId = $this->as(Role::Disponent)->postJson('/api/trips', $tripBody(400, 'Wien'))->assertCreated()->json('id');
        $quote = $this->quote('München – Wien', $this->customer('Reisebüro'), $tripId);
        $journeyId = $quote['journey_id'];

        // vor dem Festschreiben: der aktuelle Stand der Tour
        $this->as(Role::Disponent)->getJson("/api/journeys/$journeyId")->assertJsonPath('sections.0.trip.frozen_by', null);

        $number = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk()->json('number');
        $this->as(Role::Disponent)->putJson("/api/trips/$tripId", $tripBody(520, 'Graz'))->assertOk();

        $this->as(Role::Disponent)->getJson("/api/journeys/$journeyId")->assertOk()
            ->assertJsonPath('sections.0.trip.loaded_km', 400)
            ->assertJsonPath('sections.0.trip.to', 'Wien')
            ->assertJsonPath('sections.0.trip.frozen_by', $number)
            ->assertJsonPath('sections.0.trip.id', $tripId);
    }

    public function test_tour_archiv_zaehlt_die_verwendung_in_reisen(): void
    {
        $tripId = $this->as(Role::Disponent)->postJson('/api/trips', [
            'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => 100, 'empty_km' => 0, 'drive_s' => 3600, 'elapsed_s' => 7200, 'days' => 1,
            'stops' => [['label' => 'München', 'lat' => 48.1, 'lon' => 11.5], ['label' => 'Wien', 'lat' => 48.2, 'lon' => 16.3]],
        ])->assertCreated()->json('id');

        $this->as(Role::Disponent)->getJson('/api/trips')->assertJsonPath('data.0.sections_count', 0);
        $this->quote('München – Wien', null, $tripId);
        $this->as(Role::Disponent)->getJson('/api/trips')->assertJsonPath('data.0.sections_count', 1);
    }
}
