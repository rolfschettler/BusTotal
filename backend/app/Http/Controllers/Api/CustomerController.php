<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Erlaubte Sortierungen: Name der Spalte in der Oberfläche => Datenbankausdrücke.
     * NUR was hier steht, wird je sortiert. Der Wert aus der Anfrage wählt nur einen Eintrag aus (feste Liste),
     * er gelangt nie selbst in die Abfrage. So kann niemand über die Adresse eigenes SQL einschleusen.
     */
    private const SORTS = [
        'name' => ["COALESCE(NULLIF(company_name, ''), last_name, customer_number)"],
        'number' => ['customer_number'],
        'type' => ['type'],
        'city' => ['city', 'postal_code'],
        'email' => ['email'],
        'phone' => ['phone'],
    ];

    /** Liste mit Suche, Filter, Sortierung und Seiten (je 20 Kunden). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string'],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Customer::query();

        if ($request->boolean('archived')) {
            $query->onlyTrashed();
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($q = trim((string) $request->query('q'))) {
            // % und _ sind in LIKE Platzhalter – hier wörtlich gemeint.
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like) {
                foreach (['company_name', 'first_name', 'last_name', 'customer_number', 'email', 'city', 'postal_code'] as $col) {
                    $w->orWhere($col, 'like', $like);
                }
                $w->orWhere('debitor_number', 'like', $like);
            });
        }

        // Standard: nach Name aufsteigend. Leere Werte stehen immer am Ende (in beiden Richtungen), und die
        // Kundennummer entscheidet bei Gleichstand: Ohne eindeutige Reihenfolge könnte ein Kunde beim Blättern
        // auf zwei Seiten erscheinen oder fehlen.
        $sort = $request->query('sort') ?: 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        foreach (self::SORTS[$sort] as $expression) {
            $query->orderByRaw("({$expression}) IS NULL")->orderByRaw("{$expression} {$dir}");
        }

        $page = $query
            ->orderBy('customer_number')
            ->paginate(20)
            ->withQueryString();

        return response()->json($page);
    }

    public function store(CustomerRequest $request, CustomerService $customers): JsonResponse
    {
        // Nummern und Kunde in einer Transaktion (siehe CustomerService).
        $customer = $customers->create($request->validated());

        return response()->json($customer->load(['contacts', 'addresses']), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer->load(['contacts', 'addresses']));
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json($customer->fresh(['contacts', 'addresses']));
    }

    /** "Löschen" bedeutet Archivieren: Der Kunde bleibt für Belege erhalten (GoBD). */
    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Kunde archiviert.']);
    }

    public function restore(string $id): JsonResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);
        $customer->restore();

        return response()->json($customer->fresh(['contacts', 'addresses']));
    }

    /** Ähnliche Kunden finden, bevor ein neuer angelegt wird. */
    public function duplicates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'ignore' => ['nullable', 'uuid'],
        ]);

        $hits = collect();
        $base = fn () => Customer::query()->when($data['ignore'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id));

        if (! empty($data['email'])) {
            $base()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->get()
                ->each(fn ($c) => $hits->put($c->id, ['customer' => $c, 'reason' => 'Gleiche E-Mail-Adresse']));
        }

        if (! empty($data['company_name'])) {
            $base()->whereRaw('LOWER(company_name) = ?', [mb_strtolower(trim($data['company_name']))])->get()
                ->each(fn ($c) => $hits->put($c->id, ['customer' => $c, 'reason' => 'Gleicher Firmenname']));
        }

        if (! empty($data['last_name']) && ! empty($data['postal_code'])) {
            $base()
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($data['last_name']))])
                ->whereRaw('LOWER(COALESCE(first_name, \'\')) = ?', [mb_strtolower(trim($data['first_name'] ?? ''))])
                ->where('postal_code', trim($data['postal_code']))->get()
                ->each(fn ($c) => $hits->put($c->id, ['customer' => $c, 'reason' => 'Gleicher Name und PLZ']));
        }

        return response()->json($hits->values()->take(5));
    }

    /** DSGVO Art. 15: alle gespeicherten Daten eines Kunden als Datei. */
    public function export(Customer $customer): JsonResponse
    {
        AuditLog::record('exported', $customer);

        $customer->load(['contacts', 'addresses']);
        $history = AuditLog::where('auditable_type', $customer->getMorphClass())
            ->where('auditable_id', $customer->id)
            ->orderBy('id')->get(['event', 'created_at']);

        return response()->json([
            'exportiert_am' => now()->toIso8601String(),
            'kunde' => $customer,
            'ansprechpartner' => $customer->contacts,
            'adressen' => $customer->addresses,
            'aenderungsverlauf' => $history,
        ])->header('Content-Disposition', 'attachment; filename="kunde-'.$customer->customer_number.'.json"');
    }

    /**
     * DSGVO Art. 17: personenbezogene Daten entfernen. Kunden- und Debitorennummer bleiben
     * erhalten, weil Rechnungen sie aufbewahren müssen (GoBD, 10 Jahre).
     */
    public function anonymize(Customer $customer): JsonResponse
    {
        DB::transaction(function () use ($customer) {
            $customer->contacts()->delete();
            $customer->addresses()->delete();

            // saveQuietly: kein normaler Audit-Eintrag, sonst stünden die alten Daten im Protokoll.
            $customer->forceFill([
                'type' => $customer->type,
                'company_name' => $customer->company_name ? 'Anonymisiert' : null,
                'salutation' => null,
                'first_name' => null,
                'last_name' => $customer->company_name ? null : 'Anonymisiert',
                'email' => null, 'phone' => null, 'mobile' => null, 'website' => null, 'vat_id' => null,
                'street' => null, 'postal_code' => null, 'city' => null,
                'notes' => null,
            ])->saveQuietly();
            $customer->delete();

            AuditLog::record('anonymized', $customer);
        });

        return response()->json(['message' => 'Kunde anonymisiert.']);
    }

    /** Auswahlliste für das Formular. */
    public function types(): JsonResponse
    {
        return response()->json(collect(CustomerType::cases())->map(fn ($t) => [
            'value' => $t->value,
            'label' => match ($t) {
                CustomerType::Firma => 'Firma',
                CustomerType::Privat => 'Privatperson',
                CustomerType::Schule => 'Schule',
                CustomerType::Verein => 'Verein',
                CustomerType::Reisebuero => 'Reisebüro',
            },
            'organisation' => $t->isOrganisation(),
        ]));
    }
}
