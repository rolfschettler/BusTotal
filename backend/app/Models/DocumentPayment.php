<?php

namespace App\Models;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Zahlungsbuchung zu einer Rechnung oder Anzahlungsrechnung. Wird nie gelöscht, nur storniert
 * (cancelled_at mit Grund), damit der Zahlungsverlauf nachvollziehbar bleibt (GoBD). Die Buchung ändert den Beleg nicht.
 */
class DocumentPayment extends Model
{
    use Auditable, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'method' => PaymentMethod::class,
            'paid_on' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
