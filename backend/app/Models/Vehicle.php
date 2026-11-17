<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDeadlines;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use Auditable, HasDeadlines, HasFactory, HasUuids, SoftDeletes;

    /** Spalte => Bezeichnung. Die Reihenfolge ist die Reihenfolge in der Oberfläche. */
    public const DEADLINES = [
        'hu_due_date' => 'Hauptuntersuchung (HU)',
        'sp_due_date' => 'Sicherheitsprüfung (SP)',
        'tachograph_due_date' => 'Fahrtenschreiber',
        'service_due_date' => 'Wartung',
    ];

    protected $guarded = ['id'];

    protected $appends = ['display_name', 'deadlines', 'next_deadline'];

    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'equipment' => 'array',
            'seats' => 'integer',
            'year_built' => 'integer',
            'gross_weight_kg' => 'integer',
            'length_m' => 'float',
            'width_m' => 'float',
            'height_m' => 'float',
            'cost_per_km' => 'float',
            // Y-m-d: reine Kalendertage ohne Uhrzeit und Zeitzone
            'hu_due_date' => 'date:Y-m-d',
            'sp_due_date' => 'date:Y-m-d',
            'tachograph_due_date' => 'date:Y-m-d',
            'service_due_date' => 'date:Y-m-d',
        ];
    }

    /** "B-01 · M-AB 123" */
    public function getDisplayNameAttribute(): string
    {
        return $this->fleet_number.' · '.$this->license_plate;
    }
}
