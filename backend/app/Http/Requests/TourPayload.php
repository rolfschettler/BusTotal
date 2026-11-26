<?php

namespace App\Http\Requests;

use App\Enums\TripKind;
use Illuminate\Validation\Rule;

/**
 * Prüfregeln für eine berechnete Tour, die mit einem Angebot oder einer weiteren Strecke mitgeschickt wird (Kopie an der Strecke
 * der Reise, ohne Eintrag im Tour-Archiv). Dieselbe Form wie TourSnapshotService::tripData(), siehe ::fromRequest().
 */
final class TourPayload
{
    /** @return array<string, list<mixed>> Regeln mit dem Schlüssel `$key` für die Tour und `$key.*` für ihre Teile */
    public static function rules(string $key = 'tour'): array
    {
        return [
            $key => ['nullable', 'array'],
            "$key.title" => ['nullable', 'string', 'max:150'],
            "$key.trip_kind" => ['nullable', Rule::enum(TripKind::class)],
            "$key.loaded_km" => ["required_with:$key", 'numeric', 'min:0', 'max:50000'],
            "$key.empty_km" => ["required_with:$key", 'numeric', 'min:0', 'max:50000'],
            "$key.drive_s" => ["required_with:$key", 'integer', 'min:0', 'max:3000000'],
            "$key.elapsed_s" => ['nullable', 'integer', 'min:0', 'max:6000000'],
            "$key.days" => ["required_with:$key", 'integer', 'min:1', 'max:60'],
            "$key.round_trip" => ['nullable', 'boolean'],
            "$key.stops" => ['nullable', 'array', 'max:40'],
            "$key.stops.*.label" => ['required', 'string', 'max:255'],
            "$key.stops.*.dwell_minutes" => ['nullable', 'integer', 'min:0', 'max:1440'],
            "$key.stops.*.driver_free" => ['nullable', 'boolean'],
            "$key.legs" => ['nullable', 'array', 'max:80'],
            "$key.legs.*.from" => ['required', 'string', 'max:255'],
            "$key.legs.*.to" => ['required', 'string', 'max:255'],
            "$key.legs.*.distance_m" => ['required', 'integer', 'min:0', 'max:50000000'],
            "$key.legs.*.duration_s" => ['required', 'integer', 'min:0', 'max:3000000'],
            "$key.legs.*.empty" => ['nullable', 'boolean'],
            // Plan zum Wiederöffnen der Strecke (SectionPlanService): Orte mit Lage, Betriebshof, Ergebnis mit Kartenlinie
            "$key.plan" => ['nullable', 'array'],
            "$key.plan.depot_id" => ['nullable', 'uuid', 'exists:depots,id'],
            "$key.plan.depot_other" => ['nullable', 'array'],
            "$key.plan.depot_other.label" => ["required_with:$key.plan.depot_other", 'string', 'max:255'],
            "$key.plan.depot_other.lat" => ["required_with:$key.plan.depot_other", 'numeric', 'between:-90,90'],
            "$key.plan.depot_other.lon" => ["required_with:$key.plan.depot_other", 'numeric', 'between:-180,180'],
            "$key.plan.from_depot" => ['nullable', 'boolean'],
            "$key.plan.return_to_depot" => ['nullable', 'boolean'],
            "$key.plan.start_at" => ['nullable', 'date'],
            "$key.plan.stops" => ["required_with:$key.plan", 'array', 'min:1', 'max:50'],
            "$key.plan.stops.*.label" => ['required', 'string', 'max:255'],
            "$key.plan.stops.*.lat" => ['required', 'numeric', 'between:-90,90'],
            "$key.plan.stops.*.lon" => ['required', 'numeric', 'between:-180,180'],
            "$key.plan.stops.*.dwell_minutes" => ['nullable', 'integer', 'min:0', 'max:10080'],
            "$key.plan.stops.*.driver_free" => ['nullable', 'boolean'],
            "$key.plan.route" => ['nullable', 'array'],
            "$key.plan.geometry" => ['nullable', 'array', 'max:200000'],
        ];
    }
}
