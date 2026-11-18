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

    public function route(array $points, ?VehicleLimits $limits = null): RouteResult
    {
        if (count($points) < 2) {
            throw new RoutingException('Für eine Route braucht es mindestens zwei Orte.', RoutingException::REJECTED);
        }

        $body = [
            'coordinates' => array_map(fn (Coordinate $c) => [$c->lon, $c->lat], $points),
            'language' => 'de',
            'instructions' => false,
            'geometry_simplify' => true,
        ];
        if ($restrictions = $limits?->toRestrictions()) {
            $body['options'] = ['profile_params' => ['restrictions' => $restrictions]];
        }

        $factor = (float) config('services.openrouteservice.duration_factor', 1.0);

        return $this->remember('ors:route:'.sha1(json_encode([$body, $factor])), self::ROUTE_TTL, function () use ($body, $factor) {
            $json = $this->send('post', '/v2/directions/driving-hgv/geojson', $body);

            $feature = $json['features'][0] ?? null;
            if (! $feature || ! isset($feature['properties']['summary'])) {
                throw new RoutingException('Der Routing-Dienst hat keine Route geliefert.', RoutingException::UNAVAILABLE);
            }

            $legs = array_map(fn (array $s) => [
                'distance_m' => (int) round($s['distance']),
                'duration_s' => (int) ceil($s['duration'] * $factor),
            ], $feature['properties']['segments'] ?? []);

            return new RouteResult(
                (int) round($feature['properties']['summary']['distance']),
                (int) ceil($feature['properties']['summary']['duration'] * $factor),
                $legs,
                array_map(fn (array $p) => [(float) $p[1], (float) $p[0]], $feature['geometry']['coordinates'] ?? []),
            );
        });
    }

    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $limit = max(1, min(10, $limit));

        return $this->remember('ors:geocode:'.sha1(mb_strtolower($query).'|'.$limit), self::GEOCODE_TTL, function () use ($query, $limit) {
            $json = $this->send('get', '/geocode/search', ['text' => $query, 'size' => $limit, 'lang' => 'de']);

            $hits = [];
            foreach ($json['features'] ?? [] as $f) {
                $coords = $f['geometry']['coordinates'] ?? null;
                $p = $f['properties'] ?? [];
                if (! is_array($coords) || count($coords) < 2 || ! isset($p['label'])) {
                    continue;
                }
                $hits[] = new GeocodeHit(
                    (string) $p['label'],
                    new Coordinate((float) $coords[1], (float) $coords[0]),
                    $p['postalcode'] ?? null,
                    $p['locality'] ?? $p['localadmin'] ?? null,
                    $p['country_a'] ?? null,
                );
            }

            return $hits;
        });
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $data): array
    {
        $key = config('services.openrouteservice.key');
        if (! $key) {
            throw new RoutingException('Die Streckenplanung ist noch nicht eingerichtet (es fehlt der Zugang zum Routing-Dienst). Bitte den Betreiber informieren.', RoutingException::NOT_CONFIGURED);
        }

        try {
            $request = Http::baseUrl(rtrim((string) config('services.openrouteservice.base_url'), '/'))
                ->withHeaders(['Authorization' => $key])
                ->acceptJson()
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

        throw $this->failure($response);
    }

    private function failure(Response $response): RoutingException
    {
        $status = $response->status();
        $code = $response->json('error.code');
        // Ins Protokoll nur Status und Fehlercode des Dienstes, weder Schlüssel noch Adressen.
        Log::warning('Routing-Dienst lehnt ab', ['status' => $status, 'code' => $code]);

        return match (true) {
            $status === 404 || $code === 2010 => new RoutingException('Für einen der Orte wurde keine befahrbare Straße in der Nähe gefunden. Bitte den Ort genauer wählen.', RoutingException::NO_ROUTE),
            $status === 401 || $status === 403 => new RoutingException('Der Zugang zum Routing-Dienst wurde abgelehnt. Bitte den Betreiber informieren.', RoutingException::NOT_CONFIGURED),
            $status === 429 => new RoutingException('Das Kontingent des Routing-Dienstes ist erreicht. Bitte später noch einmal versuchen.', RoutingException::LIMIT),
            $status === 400 => new RoutingException('Der Routing-Dienst kann diese Route nicht berechnen (zum Beispiel zu weit oder zu viele Halte).', RoutingException::REJECTED),
            default => new RoutingException('Der Routing-Dienst hat einen Fehler gemeldet. Bitte später noch einmal versuchen.', RoutingException::UNAVAILABLE),
        };
    }

    /** Zwischenspeicher mit Rückfall: Ein defekter Speicher darf die Streckenplanung nicht lahmlegen. */
    private function remember(string $key, int $ttl, callable $compute): mixed
    {
        try {
            $hit = Cache::get($key);
            if ($hit !== null) {
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
