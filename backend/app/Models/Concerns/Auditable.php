<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * An ein Modell hängen, damit Anlegen, Ändern und Löschen protokolliert werden.
 * Felder, die nie im Protokoll landen sollen (Passwörter), in $auditExclude eintragen.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('created', $model, null, $model->auditValues($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditValues($model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->auditValues($model->getRawOriginal()), $changes);
            AuditLog::record('updated', $model, $old, $changes);
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model, $model->auditValues($model->getAttributes()), null);
        });
    }

    protected function auditValues(array $values): array
    {
        return array_diff_key($values, array_flip($this->auditExclude ?? []));
    }
}
