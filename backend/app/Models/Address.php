<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use Auditable;

    protected $guarded = ['id', 'customer_id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
