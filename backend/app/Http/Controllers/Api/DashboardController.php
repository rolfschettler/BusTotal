<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Customer;
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
