<?php

namespace App\Http\Controllers\Api;

use App\Enums\AbsenceType;
use App\Enums\EmploymentType;
use App\Enums\LicenseClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriverRequest;
use App\Models\Driver;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    /** Die früheste der Fristen. Leere Fristen zählen nicht (LEAST liefert sonst NULL, sobald eine Spalte leer ist). */
    private const NEXT_DEADLINE = "NULLIF(LEAST(COALESCE(license_due_date, '9999-12-31'), COALESCE(pbs_due_date, '9999-12-31'), COALESCE(driver_card_due_date, '9999-12-31'), COALESCE(driver_card_read_due_date, '9999-12-31'), COALESCE(code95_due_date, '9999-12-31'), COALESCE(medical_due_date, '9999-12-31')), '9999-12-31')";

    /** Erlaubte Sortierungen (siehe CustomerController::SORTS): Die Anfrage wählt nur einen Eintrag der festen Liste. */
    private const SORTS = [
        'name' => ['last_name', 'first_name'],
        'number' => ['personnel_number'],
        'employment' => ['employment_type'],
        'city' => ['city'],
        'deadline' => [self::NEXT_DEADLINE],
    ];

    /** Liste mit Suche, Filter, Sortierung und Seiten (je 20 Fahrer). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'deadline' => ['nullable', Rule::in(['overdue', 'soon'])],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Driver::query()->withAbsentToday();

        if ($request->boolean('archived')) {
            $query->onlyTrashed();
        }

        if ($type = $request->query('employment_type')) {
            $query->where('employment_type', $type);
        }

        // Fristfilter: mindestens eine Frist ist abgelaufen bzw. läuft innerhalb ihrer Vorwarnzeit (meist 30 Tage,
        // „Fahrerkarte auslesen“ 7 Tage) oder früher ab.
        if ($deadline = $request->query('deadline')) {
            $query->where(function ($w) use ($deadline) {
                foreach (array_keys(Driver::DEADLINES) as $col) {
                    $deadline === 'overdue'
                        ? $w->orWhere($col, '<', now()->toDateString())
                        : $w->orWhere($col, '<=', now()->addDays(Driver::warnDaysOf($col))->toDateString());
                }
            });
        }

        if ($q = trim((string) $request->query('q'))) {
            // Suche am Wortanfang (Index), siehe NameSearch: „Müller“, „Hans Aachen“, „Müller,Hans“.
            NameSearch::apply($query, $q, ['last_name', 'first_name', 'city', 'personnel_number', 'email', 'license_number', 'driver_card_number']);
        }

        // Leere Werte immer am Ende; bei Gleichstand entscheidet die Personalnummer, damit beim Blättern
        // kein Fahrer auf zwei Seiten erscheint oder fehlt.
        $sort = $request->query('sort') ?: 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        foreach (self::SORTS[$sort] as $expression) {
            $query->orderByRaw("({$expression}) IS NULL")->orderByRaw("{$expression} {$dir}");
        }

        return response()->json($query->orderBy('personnel_number')->paginate(20)->withQueryString());
    }

    public function store(DriverRequest $request): JsonResponse
    {
        $driver = Driver::create($request->validated());

        return response()->json($this->loaded($driver->id), 201);
    }

    public function show(Driver $driver): JsonResponse
    {
        return response()->json($this->loaded($driver->id));
    }

    public function update(DriverRequest $request, Driver $driver): JsonResponse
    {
        $driver->update($request->validated());

        return response()->json($this->loaded($driver->id));
    }

    /** "Löschen" bedeutet Archivieren (Austritt): Der Fahrer bleibt für vergangene Fahrten und Belege erhalten. */
    public function markCardRead(Request $request, Driver $driver): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $readOn = Carbon::parse($data['date'] ?? now()->toDateString());

        $driver->update(['driver_card_read_due_date' => $readOn->copy()->addDays(Driver::CARD_READ_EVERY_DAYS)->toDateString()]);

        return response()->json($driver->fresh());
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $driver->delete();

        return response()->json(['message' => 'Fahrer archiviert.']);
    }

    public function restore(string $id): JsonResponse
    {
        Driver::onlyTrashed()->findOrFail($id)->restore();

        return response()->json($this->loaded($id));
    }

    /** Auswahllisten für das Formular. */
    public function options(): JsonResponse
    {
        $list = fn (array $cases) => collect($cases)->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]);

        return response()->json([
            'employment_types' => $list(EmploymentType::cases()),
            'license_classes' => $list(LicenseClass::cases()),
            'absence_types' => $list(AbsenceType::cases()),
            'deadlines' => collect(Driver::DEADLINES)->map(fn ($label, $col) => ['field' => $col, 'label' => $label])->values(),
            'warn_days' => Driver::WARN_DAYS,
        ]);
    }

    /** Fahrer mit Abwesenheiten und dem Kennzeichen "heute abwesend". Archivierte werden mitgeladen. */
    private function loaded(string $id): Driver
    {
        return Driver::withTrashed()->withAbsentToday()->with('absences')->findOrFail($id);
    }
}
