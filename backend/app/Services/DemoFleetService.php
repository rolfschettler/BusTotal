<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\DriverAbsence;
use App\Models\Vehicle;
use App\Support\DemoFleetGenerator;
use App\Support\ImportId;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Legt einen Demo-Fuhrpark (Fahrzeuge, Fahrer, Abwesenheiten) an und entfernt ihn wieder.
 *
 * Wie bei den Demo-Kunden (DemoDataService): feste UUIDs (ImportId) machen den Lauf wiederholbar, die Markierung
 * "is_demo" sorgt dafür, dass Entfernen nie echte Fahrzeuge oder Fahrer trifft, und es entsteht nur ein
 * Protokolleintrag je Lauf.
 *
 * Gehört ein Fuhrparknummer, ein Kennzeichen oder eine Personalnummer schon einem ECHTEN Datensatz, wird der
 * betroffene Demo-Datensatz übersprungen (und gezählt), statt am Eindeutigkeits-Schlüssel der Datenbank zu scheitern.
 */
class DemoFleetService
{
    public const SOURCE = 'demo';

    /**
     * @return array{vehicles: int, drivers: int, absences: int, skipped: int, conflicts: int}
     */
    public function generate(int $vehicles, int $drivers): array
    {
        $generator = new DemoFleetGenerator(CarbonImmutable::today());
        $stats = ['vehicles' => 0, 'drivers' => 0, 'absences' => 0, 'skipped' => 0, 'conflicts' => 0];

        for ($i = 1; $i <= $vehicles; $i++) {
            $id = ImportId::for(self::SOURCE, 'fahrzeug', $i);
            if (Vehicle::withTrashed()->whereKey($id)->exists()) {
                $stats['skipped']++;

                continue;
            }

            $data = $generator->vehicle($i);
            $taken = Vehicle::withTrashed()
                ->where(fn ($q) => $q->where('fleet_number', $data['fleet_number'])->orWhere('license_plate', $data['license_plate']))
                ->exists();
            if ($taken) {
                $stats['conflicts']++;

                continue;
            }

            $archived = $data['archived'];
            unset($data['archived']);
            $stamp = $data['created_at'];

            (new Vehicle)->forceFill(['id' => $id, 'is_demo' => true, 'deleted_at' => $archived ? $stamp : null, 'updated_at' => $stamp] + $data)->saveQuietly();
            $stats['vehicles']++;
        }

        for ($i = 1; $i <= $drivers; $i++) {
            $id = ImportId::for(self::SOURCE, 'fahrer', $i);
            if (Driver::withTrashed()->whereKey($id)->exists()) {
                $stats['skipped']++;

                continue;
            }

            $made = $generator->driver($i);
            if (Driver::withTrashed()->where('personnel_number', $made['driver']['personnel_number'])->exists()) {
                $stats['conflicts']++;

                continue;
            }

            DB::transaction(function () use ($id, $i, $made, &$stats) {
                $stamp = $made['driver']['created_at'];
                (new Driver)->forceFill(['id' => $id, 'is_demo' => true, 'deleted_at' => $made['archived'] ? $stamp : null, 'updated_at' => $stamp] + $made['driver'])->saveQuietly();

                foreach ($made['absences'] as $j => $absence) {
                    (new DriverAbsence)->forceFill(['id' => ImportId::for(self::SOURCE, 'abwesenheit', "$i-$j"), 'driver_id' => $id] + $absence)->saveQuietly();
                    $stats['absences']++;
                }
            });
            $stats['drivers']++;
        }

        if ($stats['vehicles'] + $stats['drivers'] > 0) {
            AuditLog::record('demo_fleet_created', null, null, ['vehicles' => $stats['vehicles'], 'drivers' => $stats['drivers'], 'absences' => $stats['absences']]);
        }

        return $stats;
    }

    /**
     * Entfernt alle Demo-Fahrzeuge und -Fahrer samt Abwesenheiten (auch archivierte). Echte Datensätze bleiben.
     *
     * @return array{vehicles: int, drivers: int}
     */
    public function remove(): array
    {
        $driverIds = Driver::withTrashed()->where('is_demo', true)->pluck('id');
        DriverAbsence::whereIn('driver_id', $driverIds)->delete();

        $drivers = Driver::withTrashed()->where('is_demo', true)->forceDelete();
        $vehicles = Vehicle::withTrashed()->where('is_demo', true)->forceDelete();

        if ($vehicles + $drivers > 0) {
            AuditLog::record('demo_fleet_removed', null, null, ['vehicles' => $vehicles, 'drivers' => $drivers]);
        }

        return ['vehicles' => $vehicles, 'drivers' => $drivers];
    }
}
