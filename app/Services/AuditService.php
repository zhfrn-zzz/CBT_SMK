<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    public function log(string $action, ?string $auditableType, ?int $auditableId, array $data = [], ?string $description = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'new_values' => ! empty($data) ? $data : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'description' => $description,
        ]);
    }

    public function logExport(string $resource, int $resourceId): void
    {
        $this->log('export', $resource, $resourceId, [], "Export: $resource #$resourceId");
    }

    public function logImport(string $resource, int $count): void
    {
        $this->log('import', $resource, null, ['count' => $count], "Import $count $resource records");
    }

    public function logLogin(User $user, string $ip): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => $ip,
        ]);
    }
}
