<?php

namespace App\Routing;

use App\Services\ServiceKeys;
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
 * Der Zugangsschlüssel kommt zuerst aus der Firmen-Datenbank (Einstellungen › Dienste, verschlüsselt), sonst als Rückfall aus der
 * Server-Umgebung (ORS_API_KEY in .env), siehe ServiceKeys. Er steht nie im Frontend oder in Fehlermeldungen und wird im Header
 * gesendet, nicht in der Adresse (Adressen landen in Protokollen).
 *
 * Gleiche Anfragen werden zwischengespeichert: Das schont das Tageskontingent des Dienstes. Der Zwischenspeicher
 * ist nur eine Beschleunigung; fällt er aus, wird ohne ihn gerechnet.
 */
class OpenRouteService implements GeocodingProvider, RoutingProvider
{
    public function __construct(private readonly ServiceKeys $keys) {}

    /** Ob der Schlüssel dieser Firma (nicht der allgemeine) gilt: bestimmt, wer bei Schlüsselproblemen handeln muss. */
    private function ownKey(): bool
    {
        return $this->keys->resolve(ServiceKeys::OPENROUTESERVICE)['source'] === 'own';
    }
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
        $key = $this->keys->resolve(ServiceKeys::OPENROUTESERVICE)['key'];
        if (! $key) {
            throw new RoutingException('Die Streckenplanung ist noch nicht eingerichtet: Es fehlt der Zugangsschlüssel zum Routing-Dienst. Ein Administrator trägt ihn unter Einstellungen › Dienste ein.', RoutingException::NOT_CONFIGURED);
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
                ->retry(2, 300, fn (?Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false);

            $url = $this->endpoint($path);
            $response = $method === 'get' ? $request->get($url, $data) : $request->post($url, $data);
        } catch (ConnectionException $e) {
            // cURL 28 = Zeitüberschreitung, cURL 6/7 = Name oder Server nicht erreichbar. Im Protokoll steht nur die Art, nie die Adresse.
            $timeout = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false || stripos($e->getMessage(), 'timeout') !== false;
            Log::warning('Routing-Dienst nicht erreichbar', ['area' => $area, 'art' => $timeout ? 'zeitueberschreitung' : 'keine_verbindung']);

            throw $timeout
                ? new RoutingException('Der Routing-Dienst hat nicht rechtzeitig geantwortet (er ist gerade sehr langsam oder überlastet). Bitte in einer Minute noch einmal versuchen. [Fehlercode R-ZEIT]', RoutingException::UNAVAILABLE)
                : new RoutingException('Der Server kann den Routing-Dienst nicht erreichen (Internetverbindung des Servers oder Störung beim Anbieter). Bitte in ein paar Minuten noch einmal versuchen. Hilft das nicht, den Betreiber informieren. [Fehlercode R-VERBINDUNG]', RoutingException::UNAVAILABLE);
        }

        if ($response->successful()) {
            // Eine Seite statt Daten (zum Beispiel die Hinweisseite eines Anbieters) ist kein gültiges Ergebnis.
            if (! is_array($response->json())) {
                Log::warning('Routing-Dienst antwortet ohne Daten', ['status' => $response->status(), 'area' => $area, 'typ' => $response->header('Content-Type')]);

                throw new RoutingException('Der Routing-Dienst hat eine Antwort geschickt, die die Anwendung nicht lesen kann. Das liegt nicht an Ihrer Eingabe. Bitte den Betreiber informieren. [Fehlercode R-FORMAT]', RoutingException::UNEXPECTED);
            }

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

    /**
     * Verbindungsprüfung (Einstellungen › Dienste): EINE kleine Ortssuche, bewusst am Zwischenspeicher vorbei, sonst würde ein
     * früheres Ergebnis „funktioniert“ vortäuschen. Kostet eine Anfrage.
     *
     * @throws RoutingException
     */
    public function ping(): void
    {
        $this->send('get', '/geocode/search', ['text' => 'Berlin', 'size' => 1, 'lang' => 'de']);
    }

    /** Hebt die Kontingent-Sperre auf (nach einem neuen Schlüssel: Die Sperre gehörte zum alten). */
    public function resetQuota(): void
    {
        foreach (['geocode', 'directions', 'matrix'] as $area) {
            try {
                Cache::forget($this->quotaKey($area));
            } catch (Throwable) {
                // ohne Zwischenspeicher gibt es auch keine Sperre
            }
        }
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

    /**
     * Pfad beim Dienst. Der Code kennt die Pfade der alten Adresse (/v2/directions …, /geocode/search …). Seit dem 24.11.2026 liegen
     * sie bei api.heigit.org unter /openrouteservice/v2/… und /pelias/v1/search (Vorsatz je Bereich in config/services.php).
     */
    private function endpoint(string $path): string
    {
        if (str_starts_with($path, '/geocode')) {
            return rtrim((string) config('services.openrouteservice.geocode_prefix'), '/').substr($path, strlen('/geocode'));
        }

        return rtrim((string) config('services.openrouteservice.routing_prefix'), '/').$path;
    }

    private function quotaException(): RoutingException
    {
        $who = $this->ownKey() ? 'Ihres Schlüssels' : 'des allgemeinen Schlüssels (mit anderen Firmen geteilt, ein eigener Schlüssel unter Einstellungen › Dienste vermeidet das)';

        return new RoutingException('Das Tageskontingent '.$who.' beim Routing-Dienst ist aufgebraucht. Es wird nach Mitternacht (UTC) zurückgesetzt. Bis dahin gehen nur Orte, die schon einmal gesucht wurden. Bitte später noch einmal versuchen.', RoutingException::LIMIT);
    }

    private function failure(Response $response, string $area): RoutingException
    {
        $status = $response->status();
        $code = $response->json('error.code');
        // Ins Protokoll nur Status und Fehlercode des Dienstes, weder Schlüssel noch Adressen.
        Log::warning('Routing-Dienst lehnt ab', ['status' => $status, 'code' => $code, 'area' => $area, 'typ' => $response->header('Content-Type')]);

        // "Quota exceeded" kommt als 403 (nicht 429): Das Tageskontingent ist aufgebraucht, der Schlüssel ist in Ordnung.
        if ($status === 403 && stripos((string) $response->body(), 'quota') !== false) {
            $this->blockQuota($area);

            return $this->quotaException();
        }

        return match (true) {
            // 2009: Zwischen den Punkten gibt es keinen Weg. Mit Fahrzeugmaßen liegt das oft an einem Ort hinter einer niedrigen
            // Unterführung oder Gewichtsgrenze (Erfahrung: Bahnhofsvorplatz München mit 3,65 m Höhe).
            $code === 2009 => new RoutingException('Zwischen den Orten wurde keine Route gefunden. Mit den Maßen des Fahrzeugs (Höhe, Breite, Länge, Gewicht) kann ein Ort unerreichbar sein, zum Beispiel hinter einer niedrigen Unterführung. Bitte einen Ort in der Nähe wählen (zum Beispiel einen Busparkplatz statt des Bahnhofs) oder ein anderes Fahrzeug.', RoutingException::NO_ROUTE),
            $code === 2010 || ($status === 404 && $code !== null) => new RoutingException('Für einen der Orte wurde keine befahrbare Straße in der Nähe gefunden. Bitte den Ort genauer wählen.', RoutingException::NO_ROUTE),
            // Die Adresse des Dienstes gibt es nicht mehr (abgeschaltet oder verlegt). Der Anwender kann nichts tun, der Betreiber muss die Einstellung ändern.
            $status === 410 || ($status === 404 && $code === null) || ($status >= 300 && $status < 400) => new RoutingException("Der Routing-Dienst ist unter der eingestellten Adresse nicht mehr zu erreichen (der Anbieter hat sie abgeschaltet oder verlegt). Das lässt sich in der Anwendung nicht beheben: Bitte den Betreiber informieren. [Fehlercode R{$status}]", RoutingException::OUTDATED),
            $status === 401 || $status === 403 => new RoutingException($this->ownKey()
                ? "Der Zugangsschlüssel Ihrer Firma für den Routing-Dienst wurde abgelehnt (abgelaufen, gesperrt oder falsch). Ein Administrator prüft ihn unter Einstellungen › Dienste (dort auch „Verbindung prüfen“). [Fehlercode R{$status}]"
                : "Der allgemeine Zugangsschlüssel für den Routing-Dienst wurde abgelehnt (abgelaufen, gesperrt oder falsch). Bitte den Betreiber informieren, oder ein Administrator trägt unter Einstellungen › Dienste einen eigenen Schlüssel ein. [Fehlercode R{$status}]", RoutingException::AUTH),
            // 429: zu viele Anfragen pro Minute beim Dienst. Kurzfristig, daher keine Sperre bis Mitternacht.
            $status === 429 => new RoutingException('Der Routing-Dienst ist gerade ausgelastet. Bitte in einer Minute noch einmal versuchen.', RoutingException::LIMIT),
            $status === 400 => new RoutingException('Der Routing-Dienst kann diese Route nicht berechnen (zum Beispiel zu weit oder zu viele Halte). Bitte Orte näher beieinander wählen oder Zwischenhalte entfernen.', RoutingException::REJECTED),
            // 5xx: der Dienst selbst hat ein Problem, später klappt es meist wieder.
            $status >= 500 => new RoutingException("Der Routing-Dienst hat selbst ein Problem (Fehler auf Seiten des Anbieters). Bitte in ein paar Minuten noch einmal versuchen. Hilft das nicht, den Betreiber informieren. [Fehlercode R{$status}]", RoutingException::UNAVAILABLE),
            // Alles andere (406, 415, 405 …): Die Anfrage der Anwendung passt nicht zum Dienst. Das ist kein Eingabefehler.
            default => new RoutingException("Der Routing-Dienst hat die Anfrage der Anwendung nicht angenommen. Das liegt nicht an Ihrer Eingabe. Bitte den Betreiber informieren und den Fehlercode nennen. [Fehlercode R{$status}]", RoutingException::UNEXPECTED),
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
