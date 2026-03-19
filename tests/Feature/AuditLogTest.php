<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_log_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get('/admin/audit-log');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/AuditLog/Index'));
    }

    public function test_non_admin_cannot_view_audit_log(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $this->actingAs($guru);

        $response = $this->get('/admin/audit-log');
        $response->assertStatus(403);
    }

    public function test_audit_log_created_on_model_create(): void
    {
        User::factory()->create(['role' => 'admin']);

        // Creating a user should create an audit log
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_audit_log_filters_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AuditLog::create([
            'action' => 'login',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($admin);
        $response = $this->get('/admin/audit-log?action=login');
        $response->assertStatus(200);
    }
}
