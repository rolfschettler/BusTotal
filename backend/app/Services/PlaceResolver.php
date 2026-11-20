<?php

namespace App\Services;

use App\Models\Place;
use App\Routing\GeocodingProvider;
use App\Routing\RoutingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Macht aus einer Adresse einen gespeicherten Ort (places). DIE Stelle, die den Adressdienst für gespeicherte Datensätze anruft:
 * Eine Adresse, die schon einmal gesucht wurde (auch von einem anderen Datensatz), kostet keine Anfrage mehr. Das schont das
 * gemeinsame Kontingent des Dienstes (siehe docs/STRECKENPLANUNG.md).
 *
 * Nicht für Eingaben, die nicht gespeichert werden (Halte der Streckenplanung): Dort können Adressen von Privatpersonen stehen.
 */
class PlaceResolver
{
    public function __construct(private readonly GeocodingProvider $provider) {}

    /**
     * Setzt den Ort eines Datensatzes (Modell mit `HasPlace`), wenn nötig. Speichern muss der Aufrufer.
     *
     * @param  string  $field  Feld, das bei "nicht gefunden" als fehlerhaft gemeldet wird
     *
     * @throws ValidationException wenn zur Adresse kein Ort gefunden wird
     * @throws RoutingException wenn der Dienst nicht antwortet, nicht eingerichtet ist oder sein Kontingent erreicht ist
     */
    public function assign(Model $record, string $field = 'address'): void
    {
        if (! $record->needsPlace()) {
            return;
        }

        $query = trim((string) $record->placeQuery());
        // Adresse entfernt: Der alte Ort gehört nicht mehr dazu.
        $record->place_id = $query === '' ? null : $this->resolve($query, $field)->id;
        $record->unsetRelation('place');
    }

    /** Der Ort zu einer Adresse: aus der Datenbank, sonst einmal beim Dienst gesucht und gespeichert. */
    public function resolve(string $query, string $field = 'address'): Place
    {
        $key = $this->key($query);

        if ($known = Place::where('lookup_key', $key)->first()) {
            return $known;
        }

        $hit = $this->provider->search($query, 1)[0] ?? null;
        if ($hit === null) {
            throw ValidationException::withMessages([$field => ['Zu dieser Adresse wurde kein Ort gefunden. Bitte anders schreiben, zum Beispiel mit Straße, Postleitzahl und Ort.']]);
        }

        try {
            return Place::create([
                'lookup_key' => $key,
                'query' => mb_substr(trim($query), 0, 255),
                'label' => mb_substr($hit->label, 0, 255),
                'lat' => $hit->coordinate->lat,
                'lon' => $hit->coordinate->lon,
                'postal_code' => $hit->postalCode,
                'city' => $hit->city,
                'country' => $hit->country,
                'source' => strtolower(class_basename($this->provider)),
                'geocoded_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Eine gleichzeitige Anfrage hat dieselbe Adresse gerade gespeichert: deren Ort nehmen.
            return Place::where('lookup_key', $key)->firstOrFail();
        }
    }

    /** Gleiche Adressen mit anderer Groß- und Kleinschreibung oder zusätzlichen Leerzeichen sind derselbe Ort. */
    private function key(string $query): string
    {
        return sha1(mb_strtolower(preg_replace('/\s+/u', ' ', trim($query))));
    }
}
