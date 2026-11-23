<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Journey;
use App\Services\JourneyService;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reisen: Übersicht und Detail (nur lesen). Eine Reise entsteht mit dem ersten Angebot aus der Streckenplanung und wird über ihre
 * Belege weitergeführt; hier gibt es nichts anzulegen. Summen und Stand werden aus den Belegen abgeleitet (JourneyService).
 */
class JourneyController extends Controller
{
    public function __construct(private readonly JourneyService $journeys) {}

    /** Neueste Reisen zuerst. Die Suche trifft Titel und Kunde am Wortanfang (siehe NameSearch). */
    public function index(Request $request): JsonResponse
    {
        $query = Journey::query()
            ->select('journeys.*')
            ->with('customer:id,customer_number,company_name,first_name,last_name,postal_code,city')
            ->withCount('sections')
            ->leftJoin('customers as c', 'c.id', '=', 'journeys.customer_id');

        if ($q = trim((string) $request->query('q'))) {
            NameSearch::apply($query, $q, ['last_name', 'first_name', 'city', 'postal_code', 'customer_number', 'company_name', 'email'], 'c', function ($w, string $token) {
                $w->orWhere('journeys.title', 'like', NameSearch::start($token))
                    ->orWhere('journeys.title', 'like', '% '.NameSearch::start($token));
            });
        }

        $result = $query->orderByDesc('journeys.created_at')->orderByDesc('journeys.id')->paginate(20)->withQueryString();

        // Stand und Betrag je Reise: die Belege der ganzen Seite mit EINER Abfrage holen.
        $docs = Document::whereIn('journey_id', $result->getCollection()->pluck('id'))->get()->groupBy('journey_id');
        $result->getCollection()->each(function (Journey $j) use ($docs) {
            foreach ($this->journeys->overview($docs->get($j->id, [])) as $key => $value) {
                $j->setAttribute($key, $value);
            }
        });

        return response()->json($result);
    }

    public function show(Journey $journey): JsonResponse
    {
        return response()->json($this->journeys->detail($journey));
    }
}
