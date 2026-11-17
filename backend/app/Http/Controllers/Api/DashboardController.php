<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

/**
 * Daten für die Kacheln der Startseite. Jede Kachel hat ihre eigene Schnittstelle und ihre eigene
 * Rollenprüfung (siehe routes/api.php), damit neue Module ihre Kacheln unabhängig ergänzen können.
 */
class DashboardController extends Controller
{
    /**
     * Einrichtungs-Checkliste für den Administrator: Was fehlt noch, damit die Firma startklar ist?
     * Es werden nur Punkte aufgenommen, deren Erledigung die Software selbst erkennen kann.
     */
    public function setup(): JsonResponse
    {
        $c = CompanySetting::current();

        $items = [
            [
                'key' => 'company',
                'label' => 'Firmenname und Adresse eintragen',
                'done' => $c->name !== '' && $c->name !== 'Neue Firma' && filled($c->street) && filled($c->postal_code) && filled($c->city),
                'route' => '/einstellungen',
                'help' => 'einstellungen#firmendaten',
            ],
            [
                'key' => 'tax',
                'label' => 'Steuernummer oder USt-IdNr. eintragen',
                'done' => filled($c->tax_number) || filled($c->vat_id),
                'route' => '/einstellungen',
                'help' => 'einstellungen#firmendaten',
            ],
            [
                'key' => 'bank',
                'label' => 'Bankverbindung (IBAN) eintragen',
                'done' => filled($c->iban),
                'route' => '/einstellungen',
                'help' => 'einstellungen#iban',
            ],
            [
                'key' => 'logo',
                'label' => 'Logo hochladen',
                'done' => $c->has_logo,
                'route' => '/einstellungen',
                'help' => 'einstellungen#logo',
            ],
            [
                'key' => 'customer',
                'label' => 'Ersten Kunden anlegen',
                'done' => Customer::query()->exists(),
                'route' => '/kunden/neu',
                'help' => 'kunden#kunde-anlegen',
            ],
        ];

        return response()->json([
            'items' => $items,
            'done' => count(array_filter($items, fn ($i) => $i['done'])),
            'total' => count($items),
        ]);
    }

    /**
     * Fällige Fristen des Fuhrparks: alles, was abgelaufen ist oder in Vehicle::WARN_DAYS Tagen abläuft,
     * die dringendsten zuerst. Archivierte Fahrzeuge erscheinen nicht.
     */
    public function vehicleDeadlines(): JsonResponse
    {
        return $this->dueDeadlines(Vehicle::query()->get(), 'vehicle');
    }

    /** Fällige Fristen des Personals (Führerschein, Fahrerkarte, Code 95 …), gleiche Regeln wie beim Fuhrpark. */
    public function driverDeadlines(): JsonResponse
    {
        return $this->dueDeadlines(Driver::query()->get(), 'driver');
    }

    /**
     * Gemeinsame Antwort der Fristen-Kacheln: alle nicht in Ordnung befindlichen Fristen der Modelle,
     * die dringendsten zuerst, höchstens acht. `$name` ist der Schlüsselname ("vehicle" gibt vehicle_id und vehicle).
     */
    private function dueDeadlines($models, string $name): JsonResponse
    {
        $items = $models
            ->flatMap(fn ($m) => collect($m->deadlines)
                ->filter(fn ($d) => $d['status'] !== 'ok')
                ->map(fn ($d) => $d + [$name.'_id' => $m->id, $name => $m->display_name]))
            ->sortBy([['days', 'asc'], [$name, 'asc']])
            ->values();

        return response()->json(['total' => $items->count(), 'items' => $items->take(8)->values()]);
    }

    /** Die fünf zuletzt geänderten oder angelegten Kunden. */
    public function recentCustomers(): JsonResponse
    {
        $customers = Customer::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'customer_number', 'type', 'company_name', 'first_name', 'last_name', 'postal_code', 'city', 'updated_at']);

        return response()->json($customers);
    }
}
