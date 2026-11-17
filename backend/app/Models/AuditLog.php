<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Unveränderliches Protokoll (GoBD): Einträge werden nur angelegt, nie geändert oder gelöscht.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit-Log-Einträge sind unveränderlich.'));
        static::deleting(fn () => throw new LogicException('Audit-Log-Einträge dürfen nicht gelöscht werden.'));
    }

    /** Schreibt einen Eintrag; Benutzer, IP und Browser werden automatisch ergänzt. */
    public static function record(
        string $event,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $userId = null,
    ): self {
        $request = request();

        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
