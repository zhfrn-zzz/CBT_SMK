<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        $sensitiveFields = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

        static::created(function ($model) use ($sensitiveFields) {
            $exclude = array_merge($sensitiveFields, $model->auditExclude ?? []);
            $newValues = collect($model->getAttributes())
                ->except($exclude)
                ->toArray();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'created',
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });

        static::updated(function ($model) use ($sensitiveFields) {
            $exclude = array_merge($sensitiveFields, $model->auditExclude ?? []);
            // except() on a key-value collection removes by key, then keys() extracts the field names
            $dirty = collect($model->getDirty())->except($exclude)->keys()->all();

            if (empty($dirty)) {
                return;
            }

            $oldValues = collect($model->getOriginal())
                ->only($dirty)
                ->toArray();
            $newValues = collect($model->getAttributes())
                ->only($dirty)
                ->toArray();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'updated',
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });

        static::deleted(function ($model) use ($sensitiveFields) {
            $exclude = array_merge($sensitiveFields, $model->auditExclude ?? []);
            $oldValues = collect($model->getAttributes())
                ->except($exclude)
                ->toArray();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'old_values' => $oldValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });
    }
}
