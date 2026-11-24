<?php

namespace App\Routing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Routing und Adresssuche über openrouteservice.org (Daten von OpenStreetMap). Das Bus-/Lkw-Profil
 * (driving-hgv) berücksichtigt Höhe, Breite, Länge und Gewicht des Fahrzeugs.
 *
 * Der Zugangsschlüssel steht nur in der Server-Umgebung (ORS_API_KEY in .env), nie in der Datenbank, im Frontend
 * oder in Fehlermeldungen. Er wird im Header gesendet, nicht in der Adresse (Adressen landen in Protokollen).
 *
 * Gleiche Anfragen werden zwischengespeichert: Das schont das Tageskontingent des Dienstes. Der Zwischenspeicher
 * ist nur eine Beschleunigung; fällt er aus, wird ohne ihn gerechnet.
 */
class OpenRouteService implements GeocodingProvider, RoutingProvider
{
    private const ROUTE_TTL = 12 * 3600;

    private const GEOCODE_TTL = 7 * 86400;

    private const MAX_GEOMETRY_POINTS = 1500;

    private const GEOCODE_FETCH = 8;

    /** Treffer mit geringerer Sicherheit gelten als geraten und werden nicht angeboten. */

    private const MIN_CONFIDENCE = 0.5;

    // Die Zwischenspeicher-Schlüssel tragen eine Formatversion ("ors:v2:..."). Ändert sich, WAS abgelegt wird, die Version erhöhen:
    // Einträge des alten Formats (v1 waren Objekte, die sich nicht zurücklesen ließen) werden dann nie mehr gelesen.

    public function route(array $points, ?VehicleLimits $limits = null): RouteResult
    {
        if (count($points) < 2) {
            throw new RoutingException('Für eine Route braucht es mindestens zwei Orte.', RoutingException::REJECTED);
        }

        // WICHTIG: Weder "instructions": false noch "geometry_simplify" setzen. Ohne Anweisungen liefert der Dienst keine
        // Abschnitte ("segments") mit Strecke und Fahrzeit je Halt, die Tourberechnung braucht sie aber (Leerfahrten, Zeitplan).
        $body = [
            'coordinates' => array_map(fn (Coordinate $c) => [$c->lon, $c->lat], $points),
            'language' => 'de',
        ];
        if ($restrictions = $limits?->toRestrictions()) {
            $body['options'] = ['profile_params' => ['restrictions' => $restrictions]];
        }

        $factor = (float) config('services.openrouteservice.duration_factor', 1.0);

        // Im Zwischenspeicher liegen nur einfache Arrays, nie Objekte: Laravel gibt eigene Klassen beim Zurücklesen nicht frei
        // ("incomplete object"), das wäre bei jeder wiederholten Anfrage ein Serverfehler.
        $data = $this->remember('ors:v2:route:'.sha1(json_encode([$body, $factor])), self::ROUTE_TTL, function () use ($body, $factor) {
            $json = $this->send('post', '/v2/directions/driving-hgv/geojson', $body, 'application/geo+json, application/json');

            $feature = $json['features'][0] ?? null;
            if (! $feature || ! isset($feature['properties']['summary'])) {
                throw new RoutingException('Der Routing-Dienst hat keine Route geliefert.', RoutingException::UNAVAILABLE);
            }

            $legs = array_map(fn (array $s) => [
                'distance_m' => (int) round($s['distance']),
                'duration_s' => (int) ceil($s['duration'] * $factor),
            ], $feature['properties']['segments'] ?? []);

            return [
                'distance_m' => (int) round($feature['properties']['summary']['distance']),
                'duration_s' => (int) ceil($feature['properties']['summary']['duration'] * $factor),
                'legs' => $legs,
                'geometry' => array_map(fn (array $p) => [(float) $p[1], (float) $p[0]], $this->thin($feature['geometry']['coordinates'] ?? [], self::MAX_GEOMETRY_POINTS)),
            ];
        });

        return new RouteResult($data['distance_m'], $data['duration_s'], $data['legs'], $data['geometry']);
    }

    public function durations(array $points): array
    {
        $body = ['locations' => array_map(fn (Coordinate $c) => [$c->lon, $c->lat], $points), 'metrics' => ['duration']];

        return $this->remember('ors:v2:matrix:'.sha1(json_encode($body)), self::ROUTE_TTL, function () use ($body) {
            $json = $this->send('post', '/v2/matrix/driving-hgv', $body);
            $rows = $json['durations'] ?? null;
            if (! is_array($rows) || count($rows) !== count($body['locations'])) {
                throw new RoutingException('Der Routing-Dienst hat keine Fahrzeiten geliefert.', RoutingException::UNAVAILABLE);
            }

            return array_map(fn (array $r) => array_map(fn ($v) => $v === null ? null : (float) $v, array_values($r)), array_values($rows));
        });
    }

    /**
     * Dünnt den Linienzug auf höchstens $max Punkte aus (jeden n-ten, Anfang und Ende bleiben). Die Karte braucht
     * keine Punkte im Meterabstand; 1000 km Strecke liefern sonst bis zu 10 000 Punkte in jeder Antwort.
     *
     * @param  list<array{float, float}>  $coordinates
     * @return list<array{float, float}>
     */
    private function thin(array $coordinates, int $max): array
    {
        $n = count($coordinates);
        if ($n <= $max) {
            return $coordinates;
        }
        $step = (int) ceil($n / $max);
        $out = [];
        for ($i = 0; $i < $n; $i += $step) {
            $out[] = $coordinates[$i];
        }
        if ($i - $step !== $n - 1) {
            $out[] = $coordinates[$n - 1];
        }

        return $out;
    }

    public function search(string $query, int $limit = 5): array
    {
        // Mehrfache Leerzeichen und Satzzeichen am Ende ändern das Ergebnis nicht, würden aber sonst je eine eigene (bezahlte) Anfrage auslösen.
        $query = rtrim((string) preg_replace('/\s+/u', ' ', trim($query)), " ,.;:-");
        if (mb_strlen($query) < 2) {
            return [];
        }
        $limit = max(1, min(10, $limit));

        $rows = $this->remember('ors:v3:geocode:'.sha1(mb_strtolower($query).'|'.self::GEOCODE_FETCH), self::GEOCODE_TTL, function () use ($query) {
            // Immer gleich viele holen (auch für limit=1): Die Sortierung unten braucht Auswahl, und der Zwischenspeicher gilt für alle.
            $json = $this->send('get', '/geocode/search', ['text' => $query, 'size' => self::GEOCODE_FETCH, 'lang' => 'de']);

            $hits = [];
            foreach ($json['features'] ?? [] as $f) {
                $coords = $f['geometry']['coordinates'] ?? null;
                $p = $f['properties'] ?? [];
                if (! is_array($coords) || count($coords) < 2 || ! isset($p['label'])) {
                    continue;
                }
                $hits[] = [
                    'label' => (string) $p['label'],
                    'lat' => (float) $coords[1],
                    'lon' => (float) $coords[0],
                    'postal_code' => $p['postalcode'] ?? null,
                    'city' => $p['locality'] ?? $p['localadmin'] ?? null,
                    'country' => $p['country_a'] ?? null,
                    // Wie sicher der Dienst ist (0 bis 1). Bei einer unbekannten Adresse rät er sonst irgendeinen Ort mit ähnlichem Namen.
                    'confidence' => isset($p['confidence']) ? (float) $p['confidence'] : null,
                ];
            }

            return $hits;
        });

        // Ratetreffer weglassen: Kennt der Dienst eine Adresse nicht ("Senden-Ay"), liefert er Gebiete wie "Irrawaddy, Myanmar" mit
        // Sicherheit 0,3. Besser "nichts gefunden" (der Nutzer schreibt genauer) als eine Strecke nach Myanmar. Ohne Angabe gilt ein Treffer.
        $rows = array_values(array_filter($rows, fn (array $r) => ($r['confidence'] ?? 1.0) >= self::MIN_CONFIDENCE));

        // Verwaltungsbezirke ("Landkreis Biberach") kommen aus den Daten als Ort und stehen bei "88400 Biberach" ganz oben,
        // gemeint ist aber die Stadt. Sie rutschen ans Ende (bleiben wählbar); sonst bleibt die Reihenfolge des Dienstes.
        $isDistrict = fn (array $r): bool => (bool) preg_match('/^(Landkreis|Stadtkreis|Kreis|Regierungsbezirk)\s/u', $r['label']);
        usort($rows, fn (array $a, array $b) => $isDistrict($a) <=> $isDistrict($b));

        return array_map(fn (array $r) => new GeocodeHit($r['label'], new Coordinate($r['lat'], $r['lon']), $r['postal_code'], $r['city'], $r['country']), array_slice($rows, 0, $limit));
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $data, string $accept = 'application/json'): array
    {
        $key = config('services.openrouteservice.key');
        if (! $key) {
            throw new RoutingException('Die Streckenplanung ist noch nicht eingerichtet (es fehlt der Zugang zum Routing-Dienst). Bitte den Betreiber informieren.', RoutingException::NOT_CONFIGURED);
        }

        // Kontingente gelten beim Dienst je Bereich (Ortssuche, Route, Fahrzeitmatrix). Ist eines aufgebraucht, gehen bis zum
        // Zurücksetzen keine Anfragen dieses Bereichs mehr hinaus; die anderen Bereiche laufen weiter.
        $area = $this->area($path);
        if ($this->quotaBlocked($area)) {
            throw $this->quotaException();
        }
        $this->count($area);

        try {
            $request = Http::baseUrl(rtrim((string) config('services.openrouteservice.base_url'), '/'))
                ->withHeaders(['Authorization' => $key])
                // Der GeoJSON-Weg der Routenberechnung verlangt application/geo+json, sonst antwortet der Dienst mit 406 (Code 2007).
                ->withHeaders(['Accept' => $accept])
                ->timeout((int) config('services.openrouteservice.timeout', 15))
                // Insgesamt 2 Versuche (einmal wiederholen), nur bei Verbindungsproblemen und Serverfehlern des Dienstes. Nie bei 4xx
                // (falscher Schlüssel, kein Weg, Kontingent erreicht): Das ändert sich durch Wiederholen nicht und kostet Kontingent.
                ->retry(2, 300, fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false);

            $response = $method === 'get' ? $request->get($path, $data) : $request->post($path, $data);
        } catch (ConnectionException) {
            throw new RoutingException('Der Routing-Dienst antwortet nicht. Bitte später noch einmal versuchen.', RoutingException::UNAVAILABLE);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->failure($response, $area);
    }

    /** Bereich eines Aufrufs für Kontingent und Zähler: geocode, directions oder matrix */
    private function area(string $path): string
    {
        return match (true) {
            str_starts_with($path, '/geocode') => 'geocode',
            str_contains($path, '/matrix') => 'matrix',
            default => 'directions',
        };
    }

    private function quotaKey(string $area): string
    {
        return 'ors:quota:'.$area;
    }

    private function quotaBlocked(string $area): bool
    {
        try {
            return (bool) Cache::get($this->quotaKey($area));
        } catch (Throwable) {
            return false;
        }
    }

    /** Sperrt den Bereich bis zum Zurücksetzen des Kontingents beim Dienst (Mitternacht UTC). */
    private function blockQuota(string $area): void
    {
        try {
            Cache::put($this->quotaKey($area), true, now('UTC')->addDay()->startOfDay());
        } catch (Throwable) {
            // Ohne Zwischenspeicher keine Sperre: Der Dienst lehnt dann eben selbst ab.
        }
    }

    /** Zählt die Anfragen je Bereich und Tag (UTC) für die Auswertung durch den Betreiber. */
    private function count(string $area): void
    {
        try {
            $key = 'ors:count:'.$area.':'.now('UTC')->toDateString();
            Cache::add($key, 0, now('UTC')->addDays(8));
            Cache::increment($key);
        } catch (Throwable) {
            // Zähler ist nur zur Information.
        }
    }

    /**
     * Anfragen an den Dienst je Bereich an einem Tag (UTC).
     *
     * @return array{geocode: int, directions: int, matrix: int}
     */
    public static function usage(?string $date = null): array
    {
        $date ??= now('UTC')->toDateString();
        $out = [];
        foreach (['geocode', 'directions', 'matrix'] as $area) {
            try {
                $out[$area] = (int) Cache::get('ors:count:'.$area.':'.$date, 0);
            } catch (Throwable) {
                $out[$area] = 0;
            }
        }

        return $out;
    }

    private function quotaException(): RoutingException
    {
        return new RoutingException('Das Tageskontingent des Routing-Dienstes ist aufgebraucht. Es wird nach Mitternacht (UTC) zurückgesetzt. Bis dahin gehen nur Orte, die schon einmal gesucht wurden. Bitte später noch einmal versuchen.', RoutingException::LIMIT);
    }

    private function failure(Response $response, string $area): RoutingException
    {
        $status = $response->status();
        $code = $response->json('error.code');
        // Ins Protokoll nur Status und Fehlercode des Dienstes, weder Schlüssel noch Adressen.
        Log::warning('Routing-Dienst lehnt ab', ['status' => $status, 'code' => $code, 'area' => $area]);

        // "Quota exceeded" kommt als 403 (nicht 429): Das Tageskontingent ist aufgebraucht, der Schlüssel ist in Ordnung.
        if ($status === 403 && stripos((string) $response->body(), 'quota') !== false) {
            $this->blockQuota($area);

            return $this->quotaException();
        }

        return match (true) {
            // 2009: Zwischen den Punkten gibt es keinen Weg. Mit Fahrzeugmaßen liegt das oft an einem Ort hinter einer niedrigen
            // Unterführung oder Gewichtsgrenze (Erfahrung: Bahnhofsvorplatz München mit 3,65 m Höhe).
            $code === 2009 => new RoutingException('Zwischen den Orten wurde keine Route gefunden. Mit den Maßen des Fahrzeugs (Höhe, Breite, Länge, Gewicht) kann ein Ort unerreichbar sein, zum Beispiel hinter einer niedrigen Unterführung. Bitte einen Ort in der Nähe wählen (zum Beispiel einen Busparkplatz statt des Bahnhofs) oder ein anderes Fahrzeug.', RoutingException::NO_ROUTE),
            $status === 404 || $code === 2010 => new RoutingException('Für einen der Orte wurde keine befahrbare Straße in der Nähe gefunden. Bitte den Ort genauer wählen.', RoutingException::NO_ROUTE),
            $status === 401 || $status === 403 => new RoutingException('Der Zugang zum Routing-Dienst wurde abgelehnt. Bitte den Betreiber informieren.', RoutingException::NOT_CONFIGURED),
            // 429: zu viele Anfragen pro Minute beim Dienst. Kurzfristig, daher keine Sperre bis Mitternacht.
            $status === 429 => new RoutingException('Der Routing-Dienst ist gerade ausgelastet. Bitte in einer Minute noch einmal versuchen.', RoutingException::LIMIT),
            $status === 400 => new RoutingException('Der Routing-Dienst kann diese Route nicht berechnen (zum Beispiel zu weit oder zu viele Halte).', RoutingException::REJECTED),
            default => new RoutingException('Der Routing-Dienst hat einen Fehler gemeldet. Bitte später noch einmal versuchen.', RoutingException::UNAVAILABLE),
        };
    }

    /** Zwischenspeicher mit Rückfall: Ein defekter Speicher darf die Streckenplanung nicht lahmlegen. */
    private function remember(string $key, int $ttl, callable $compute): mixed
    {
        try {
            $hit = Cache::get($key);
            // Nur Arrays gelten: Ein Eintrag anderer Art (zum Beispiel ein Objekt aus einer früheren Programmversion)
            // wird wie "nicht gefunden" behandelt und überschrieben.
            if (is_array($hit)) {
                return $hit;
            }
        } catch (Throwable) {
            return $compute();
        }

        $value = $compute();
        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable) {
            // Zwischenspeicher ist nur eine Beschleunigung.
        }

        return $value;
    }
}
