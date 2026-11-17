<?php

namespace App\Models;

use App\Enums\AbsenceType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAbsence extends Model
{
    use Auditable, HasUuids;

    protected $guarded = ['id', 'driver_id'];

    protected function casts(): array
    {
        return [
            'type' => AbsenceType::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
