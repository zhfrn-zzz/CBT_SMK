<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

class AuditLogoutListener
{
    public function handle(Logout $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        if (! $user) {
            return;
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'description' => "User {$user->name} logged out",
        ]);
    }
}
