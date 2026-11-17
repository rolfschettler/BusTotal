<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Protokoll der Betreiber-Aktionen (Firma angelegt, gesperrt ...). Nur anfügen, nie ändern oder löschen. */
class SuperadminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function getConnectionName(): string
    {
        return config('tenancy.central_connection');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Protokolleinträge sind unveränderlich.'));
        static::deleting(fn () => throw new LogicException('Protokolleinträge dürfen nicht gelöscht werden.'));
    }

    /** Passwörter gehören nie in $details. */
    public static function record(string $event, ?string $superadminId = null, ?string $tenantId = null, ?array $details = null): self
    {
        $request = request();

        return static::create([
            'superadmin_id' => $superadminId ?? $request?->user('superadmin')?->getKey(),
            'event' => $event,
            'tenant_id' => $tenantId,
            'details' => $details,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
