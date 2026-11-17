<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use Auditable, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'active' => 'boolean'];
    }
}
