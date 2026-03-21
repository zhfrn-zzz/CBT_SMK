<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;

// === Login/Logout/Failed Login Audit ===

it('logs login event to audit log', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Simulate login event
    event(new Login('web', $user, false));

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);
});

it('logs logout event to audit log', function () {
    $user = User::factory()->create(['role' => 'guru']);

    event(new Logout('web', $user));

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'logout',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);
});

it('logs failed login attempt to audit log', function () {
    event(new Failed('web', null, ['email' => 'hacker@example.com']));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'failed_login',
        'user_id' => null,
    ]);

    $log = AuditLog::where('action', 'failed_login')->first();
    expect($log->description)->toContain('hacker@example.com');
});

// === StudentAnswer Audit ===

it('audits student answer score changes', function () {
    $guru = User::factory()->guru()->create();
    $this->actingAs($guru);

    $answer = \App\Models\StudentAnswer::factory()->create([
        'score' => 50,
        'is_correct' => false,
    ]);

    // Clear audit logs from creation
    AuditLog::truncate();

    $answer->update(['score' => 80, 'is_correct' => true]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'updated',
        'auditable_type' => \App\Models\StudentAnswer::class,
        'auditable_id' => $answer->id,
    ]);

    $log = AuditLog::where('action', 'updated')
        ->where('auditable_type', \App\Models\StudentAnswer::class)
        ->first();

    expect($log->old_values)->toHaveKey('score');
    expect($log->new_values)->toHaveKey('score');
});

// === Audit Export ===

it('allows admin to trigger audit log export', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin/audit-log/export');

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

it('forbids non-admin from exporting audit log', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get('/admin/audit-log/export');

    $response->assertStatus(403);
});

// === Audit Cleanup Command ===

it('cleans up old audit logs', function () {
    // Clear any audit logs from User creation via Auditable trait
    AuditLog::query()->delete();

    // Create logs and manually set created_at via DB
    $oldId = \Illuminate\Support\Facades\DB::table('audit_logs')->insertGetId([
        'action' => 'login',
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(400),
    ]);
    $recentId = \Illuminate\Support\Facades\DB::table('audit_logs')->insertGetId([
        'action' => 'login',
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(100),
    ]);

    expect(AuditLog::count())->toBe(2);

    $this->artisan('audit:cleanup', ['--days' => 365])
        ->assertExitCode(0);

    // Only the 400-day-old log should be deleted
    expect(AuditLog::count())->toBe(1);
    expect(AuditLog::first()->id)->toBe($recentId);
});

it('reports no logs to cleanup when all are recent', function () {
    AuditLog::create([
        'action' => 'login',
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('audit:cleanup', ['--days' => 365])
        ->expectsOutput('No audit logs to cleanup.')
        ->assertExitCode(0);
});

// === Audit Log includes new action types ===

it('shows login and logout action types in filter options', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin/audit-log');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('actionTypes', fn ($types) => $types
            ->etc()
        )
    );

    // Verify specific types are present
    $props = $response->original->getData()['page']['props'];
    $actionTypes = $props['actionTypes'];
    expect($actionTypes)->toContain('login');
    expect($actionTypes)->toContain('logout');
    expect($actionTypes)->toContain('failed_login');
});
