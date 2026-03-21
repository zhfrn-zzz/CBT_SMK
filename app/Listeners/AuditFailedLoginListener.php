<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

class AuditFailedLoginListener
{
    public function handle(Failed $event): void
    {
        $identifier = $event->credentials['email'] ?? $event->credentials['username'] ?? 'unknown';

        AuditLog::create([
            'user_id' => null,
            'action' => 'failed_login',
            'auditable_type' => User::class,
            'auditable_id' => null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'description' => "Failed login attempt for: {$identifier}",
        ]);
    }
}
