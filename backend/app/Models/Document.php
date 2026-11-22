<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Beleg (Angebot, Auftragsbestätigung, Rechnung …). Ein festgeschriebener Beleg ist unveränderlich:
 * Das Modell verweigert jede Änderung außer dem Verweis auf den Storno-Beleg, und das Löschen ganz.
 */
class Document extends Model
{
    use Auditable, HasUuids;

    /** Nur diese Felder kommen aus Formularen. Alles andere setzt der DocumentService. */
    protected $fillable = [
        'customer_id', 'subject', 'intro_text', 'footer_text', 'notes',
        'document_date', 'service_date_from', 'service_date_to', 'payment_term_days',
    ];

    /** Felder, die auch nach dem Festschreiben noch gesetzt werden dürfen. */
    private const MUTABLE_AFTER_FINALIZE = [
        'cancelled_by_id', 'updated_at', 'archive_path', 'archive_sha256', 'archived_at',
        'einvoice_path', 'einvoice_sha256', 'einvoice_format', 'einvoice_at',
    ];

    /** Archiv-Angaben: Pfad und Prüfsumme gehören nicht in die Schnittstelle. */
    protected $hidden = ['archive_path', 'archive_sha256', 'einvoice_path', 'einvoice_sha256'];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'seller' => 'array',
            'recipient' => 'array',
            'tax_summary' => 'array',
            'deductions' => 'array',
            'document_date' => 'date:Y-m-d',
            'service_date_from' => 'date:Y-m-d',
            'service_date_to' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'finalized_at' => 'datetime',
            'archived_at' => 'datetime',
            'einvoice_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Document $document) {
            if ($document->getRawOriginal('status') !== DocumentStatus::Festgeschrieben->value) {
                return;
            }
            $forbidden = array_diff(array_keys($document->getDirty()), self::MUTABLE_AFTER_FINALIZE);
            // Das Archiv-PDF darf nur einmal gesetzt werden (Nacherzeugung alter Belege), nie überschrieben.
            if ($document->getRawOriginal('archive_path') !== null
                && array_intersect(array_keys($document->getDirty()), ['archive_path', 'archive_sha256', 'archived_at']) !== []) {
                $forbidden[] = 'archive_path';
            }
            // Ebenso die E-Rechnung: einmal gesetzt, nie überschrieben.
            if ($document->getRawOriginal('einvoice_path') !== null
                && array_intersect(array_keys($document->getDirty()), ['einvoice_path', 'einvoice_sha256', 'einvoice_format', 'einvoice_at']) !== []) {
                $forbidden[] = 'einvoice_path';
            }
            if ($forbidden !== []) {
                throw new LogicException('Ein festgeschriebener Beleg ist unveränderlich (GoBD). Bitte stornieren und neu erstellen.');
            }
        });

        static::deleting(function (Document $document) {
            if ($document->status === DocumentStatus::Festgeschrieben) {
                throw new LogicException('Ein festgeschriebener Beleg darf nicht gelöscht werden (GoBD). Bitte stornieren.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Entwurf;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('position');
    }

    /** Zahlungsbuchungen (nur bei Rechnungen und Anzahlungsrechnungen), siehe PaymentService. */
    public function payments(): HasMany
    {
        return $this->hasMany(DocumentPayment::class)->orderBy('paid_on')->orderBy('created_at');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    /** Der Storno-Beleg zu diesem Beleg (falls storniert). */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cancelled_by_id');
    }
}
