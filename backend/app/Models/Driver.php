<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDeadlines;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use Auditable, HasDeadlines, HasFactory, HasUuids, SoftDeletes;

    /** Spalte => Bezeichnung. Die Reihenfolge ist die Reihenfolge in der Oberfläche. */
    public const DEADLINES = [
        'license_due_date' => 'Führerschein',
        'pbs_due_date' => 'Fahrgastbeförderung (P-Schein)',
        'driver_card_due_date' => 'Fahrerkarte',
        'driver_card_read_due_date' => 'Fahrerkarte auslesen',
        'code95_due_date' => 'Code 95 (Weiterbildung)',
        'medical_due_date' => 'Ärztliche Untersuchung',
    ];

    /** Die Fahrerkarte muss spätestens alle 28 Tage ausgelesen werden (VO 561/2006). */
    public const CARD_READ_EVERY_DAYS = 28;

    /** Kürzere Vorwarnzeit als die üblichen 30 Tage (die Frist selbst dauert nur 28 Tage). */
    public const WARN_DAYS_OVERRIDE = ['driver_card_read_due_date' => 7];

    protected $guarded = ['id'];

    protected $appends = ['display_name', 'deadlines', 'next_deadline'];

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'license_classes' => 'array',
            'hourly_rate' => 'float',
            'birth_date' => 'date:Y-m-d',
            'hired_on' => 'date:Y-m-d',
            'license_due_date' => 'date:Y-m-d',
            'pbs_due_date' => 'date:Y-m-d',
            'driver_card_due_date' => 'date:Y-m-d',
            'driver_card_read_due_date' => 'date:Y-m-d',
            'code95_due_date' => 'date:Y-m-d',
            'medical_due_date' => 'date:Y-m-d',
        ];
    }

    /** "Nachname, Vorname" */
    public function getDisplayNameAttribute(): string
    {
        return $this->last_name.', '.$this->first_name;
    }

    public function absences(): HasMany
    {
        return $this->hasMany(DriverAbsence::class)->orderByDesc('start_date');
    }

    /** Fügt `absent_today` (Ja/Nein) hinzu: Der Fahrer hat heute Urlaub, ist krank oder in Schulung. */
    public function scopeWithAbsentToday(Builder $query): void
    {
        $today = now()->toDateString();
        $query->withExists(['absences as absent_today' => fn ($q) => $q->where('start_date', '<=', $today)->where('end_date', '>=', $today)]);
    }
}
