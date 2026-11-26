<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Document;
use App\Models\User;
use App\Services\DemoDataService;
use App\Services\DemoDocumentService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Demo-Belege: Ketten in allen Zuständen über die echten Dienste, ein zweiter Lauf legt nichts an. */
class DemoDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_belege_decken_alle_staende_ab_und_laufen_nur_einmal(): void
    {
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
        User::factory()->create(['role' => Role::Admin]);
        app(DemoDataService::class)->generate(12);

        $service = app(DemoDocumentService::class);
        $stats = $service->generate();

        $this->assertSame(10, $stats['chains'], json_encode($stats));
        $this->assertSame(0, $stats['skipped']);
        $this->assertGreaterThan(20, Document::count());
        foreach (['angebot', 'auftragsbestaetigung', 'anzahlung', 'rechnung', 'gutschrift'] as $type) {
            $this->assertTrue(Document::where('type', $type)->exists(), "Art {$type} fehlt");
        }
        $this->assertTrue(Document::where('status', 'entwurf')->exists());
        $this->assertTrue(Document::whereNotNull('cancelled_by_id')->exists());
        $this->assertTrue(\App\Models\DocumentPayment::exists());

        $before = Document::count();
        $service->generate();
        $this->assertSame($before, Document::count());
    }
}
