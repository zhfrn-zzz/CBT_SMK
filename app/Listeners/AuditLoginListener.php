<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class AuditLoginListener
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'description' => "User {$user->name} logged in",
        ]);
    }
}
