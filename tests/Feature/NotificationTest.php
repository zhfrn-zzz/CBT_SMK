<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
});

// ── GET /notifications (JSON endpoint for bell dropdown) ─────────────

test('authenticated user can fetch notifications as json', function () {
    // Create a notification directly in the DB
    DatabaseNotification::create([
        'id' => fake()->uuid(),
        'type' => 'App\\Notifications\\ExportReadyNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $this->admin->id,
        'data' => [
            'type' => 'export_ready',
            'title' => 'Export Siap Diunduh',
            'message' => 'File rapor nilai telah selesai dibuat.',
            'action_url' => '/admin/analytics/download-export/test.xlsx',
            'filename' => 'test.xlsx',
        ],
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/notifications');

    $response->assertOk()
        ->assertJsonStructure([
            'notifications' => [
                'data' => [
                    '*' => ['id', 'type', 'data', 'read_at', 'created_at'],
                ],
            ],
            'unread_count',
        ])
        ->assertJsonPath('notifications.data.0.data.type', 'export_ready')
        ->assertJsonPath('unread_count', 1);
});

test('json notifications endpoint returns empty when no notifications', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/notifications');

    $response->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(0, 'notifications.data');
});

test('unauthenticated user cannot fetch notifications', function () {
    $this->getJson('/notifications')
        ->assertUnauthorized();
});

// ── GET /notifications/unread-count (lightweight polling endpoint) ───

test('unread count endpoint returns correct count', function () {
    foreach (range(1, 3) as $i) {
        DatabaseNotification::create([
            'id' => fake()->uuid(),
            'type' => 'App\\Notifications\\MateriBaruNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $this->admin->id,
            'data' => [
                'type' => 'materi_baru',
                'title' => "Materi $i",
                'message' => 'Test',
                'action_url' => '/test',
            ],
        ]);
    }

    $this->actingAs($this->admin)
        ->getJson('/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('unread_count', 3);
});

test('unread count endpoint returns zero when all read', function () {
    $this->actingAs($this->admin)
        ->getJson('/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});

// ── GET /notifications/list (Inertia page) ──────────────────────────

test('authenticated user can view notification list page', function () {
    $this->actingAs($this->guru)
        ->get('/notifications/list')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('notifications')
        );
});

// ── POST /notifications/{id}/read ───────────────────────────────────

test('user can mark notification as read', function () {
    $notification = DatabaseNotification::create([
        'id' => fake()->uuid(),
        'type' => 'App\\Notifications\\ExportReadyNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $this->admin->id,
        'data' => [
            'type' => 'export_ready',
            'title' => 'Export Siap Diunduh',
            'message' => 'File rapor nilai telah selesai dibuat.',
            'action_url' => '/admin/analytics/download-export/test.xlsx',
        ],
    ]);

    expect($notification->read_at)->toBeNull();

    $this->actingAs($this->admin)
        ->postJson("/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJson(['success' => true]);

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

test('user cannot mark another users notification as read', function () {
    $notification = DatabaseNotification::create([
        'id' => fake()->uuid(),
        'type' => 'App\\Notifications\\ExportReadyNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $this->admin->id,
        'data' => [
            'type' => 'export_ready',
            'title' => 'Export Siap',
            'message' => 'Done.',
            'action_url' => '/admin/analytics/download-export/test.xlsx',
        ],
    ]);

    $this->actingAs($this->guru)
        ->postJson("/notifications/{$notification->id}/read")
        ->assertNotFound();
});

// ── POST /notifications/read-all ────────────────────────────────────

test('user can mark all notifications as read', function () {
    foreach (range(1, 3) as $i) {
        DatabaseNotification::create([
            'id' => fake()->uuid(),
            'type' => 'App\\Notifications\\MateriBaruNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $this->siswa->id,
            'data' => [
                'type' => 'materi_baru',
                'title' => "Materi $i",
                'message' => "Materi baru $i",
                'action_url' => "/siswa/materi/$i",
            ],
        ]);
    }

    expect($this->siswa->unreadNotifications()->count())->toBe(3);

    $this->actingAs($this->siswa)
        ->post('/notifications/read-all')
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->siswa->unreadNotifications()->count())->toBe(0);
});

// ── DELETE /notifications/{id} ──────────────────────────────────────

test('user can delete own notification', function () {
    $notification = DatabaseNotification::create([
        'id' => fake()->uuid(),
        'type' => 'App\\Notifications\\PengumumanBaruNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $this->guru->id,
        'data' => [
            'type' => 'pengumuman_baru',
            'title' => 'Pengumuman',
            'message' => 'Pengumuman baru.',
            'action_url' => '/guru/pengumuman/1',
        ],
    ]);

    $this->actingAs($this->guru)
        ->delete("/notifications/{$notification->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

// ── Export notification data structure ───────────────────────────────

test('export ready notification has valid download action_url', function () {
    $notification = DatabaseNotification::create([
        'id' => fake()->uuid(),
        'type' => 'App\\Notifications\\ExportReadyNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $this->admin->id,
        'data' => [
            'type' => 'export_ready',
            'title' => 'Export Siap Diunduh',
            'message' => 'File rapor nilai telah selesai dibuat.',
            'action_url' => '/admin/analytics/download-export/rapor-xi-rpl-1.xlsx',
            'filename' => 'rapor-xi-rpl-1.xlsx',
        ],
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/notifications');

    $data = $response->json('notifications.data.0.data');
    expect($data['type'])->toBe('export_ready');
    expect($data['action_url'])->toContain('download-export');
    expect($data['filename'])->toBe('rapor-xi-rpl-1.xlsx');
});

test('unread notifications count is cached and invalidated on mark read', function () {
    DatabaseNotification::create([
        'id' => $id = fake()->uuid(),
        'type' => 'App\\Notifications\\MateriBaruNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $this->siswa->id,
        'data' => [
            'type' => 'materi_baru',
            'title' => 'Materi Baru',
            'message' => 'Ada materi baru.',
            'action_url' => '/siswa/materi/1',
        ],
    ]);

    // Prime cache
    $this->actingAs($this->siswa)
        ->getJson('/notifications')
        ->assertJsonPath('unread_count', 1);

    // Mark as read should invalidate cache
    $this->actingAs($this->siswa)
        ->postJson("/notifications/{$id}/read")
        ->assertOk();

    // Verify cache is cleared
    expect(Cache::has("user:{$this->siswa->id}:unread_notifications"))->toBeFalse();
});

test('recent notifications are shared as inertia props', function () {
    // Create 7 notifications (only latest 5 should be shared)
    foreach (range(1, 7) as $i) {
        DatabaseNotification::create([
            'id' => fake()->uuid(),
            'type' => 'App\\Notifications\\MateriBaruNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $this->guru->id,
            'data' => [
                'type' => 'materi_baru',
                'title' => "Materi $i",
                'message' => "Materi baru $i",
                'action_url' => "/guru/materi/$i",
            ],
            'created_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $this->actingAs($this->guru)
        ->get('/notifications/list')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->where('auth.unread_notifications_count', 7)
            ->has('auth.recent_notifications', 5)
        );
});
