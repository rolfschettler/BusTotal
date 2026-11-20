<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Position eines Belegs. Bei festgeschriebenen Belegen gesperrt (Anlegen, Ändern, Löschen). */
class DocumentLine extends Model
{
    // Kein Auditable: Die Positionen eines Entwurfs werden bei jedem Speichern neu geschrieben, das würde das
    // Protokoll fluten. Der Beleg selbst (Anlegen, Festschreiben, Storno) ist protokolliert.
    use HasUuids;

    protected $fillable = ['title', 'description', 'quantity', 'unit', 'unit_price', 'tax_rate', 'tax_note'];

    protected static function booted(): void
    {
        $guard = function (DocumentLine $line) {
            $document = $line->document;
            if ($document !== null && ! $document->isDraft()) {
                throw new LogicException('Positionen eines festgeschriebenen Belegs sind unveränderlich (GoBD).');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
