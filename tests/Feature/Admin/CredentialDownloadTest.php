<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seed(\Database\Seeders\SettingSeeder::class);
});

// ── PDF Download ─────────────────────────────────────────────────────

test('admin can download credentials as PDF', function () {
    $credentials = [
        ['name' => 'Siswa Satu', 'username' => '40001', 'password' => 'abc12345'],
        ['name' => 'Siswa Dua', 'username' => '40002', 'password' => 'xyz67890'],
    ];

    $key = 'test-credential-key';
    Cache::put("credentials:{$key}", $credentials, 1800);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.credentials.pdf', $key));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    // Credentials should still be in cache (allow multiple downloads)
    expect(Cache::has("credentials:{$key}"))->toBeTrue();
});

test('admin can download credentials as Excel', function () {
    $credentials = [
        ['name' => 'Siswa Satu', 'username' => '40001', 'password' => 'abc12345'],
        ['name' => 'Siswa Dua', 'username' => '40002', 'password' => 'xyz67890'],
    ];

    $key = 'test-credential-key-excel';
    Cache::put("credentials:{$key}", $credentials, 1800);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.credentials.excel', $key));

    $response->assertOk();

    // Credentials should still be in cache (allow multiple downloads)
    expect(Cache::has("credentials:{$key}"))->toBeTrue();
});

// ── Expired Credentials ──────────────────────────────────────────────

test('expired credentials return 404 for PDF', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.credentials.pdf', 'nonexistent-key'));

    $response->assertNotFound();
});

test('expired credentials return 404 for Excel', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.credentials.excel', 'nonexistent-key'));

    $response->assertNotFound();
});

// ── Security ─────────────────────────────────────────────────────────

test('non-admin cannot download credentials', function () {
    $guru = User::factory()->guru()->create();
    $key = 'test-key';
    Cache::put("credentials:{$key}", [['name' => 'Test', 'username' => '1', 'password' => 'x']], 1800);

    $response = $this->actingAs($guru)
        ->get(route('admin.credentials.pdf', $key));

    $response->assertForbidden();
});

test('unauthenticated user cannot download credentials', function () {
    $key = 'test-key';
    Cache::put("credentials:{$key}", [['name' => 'Test', 'username' => '1', 'password' => 'x']], 1800);

    $response = $this->get(route('admin.credentials.pdf', $key));

    $response->assertRedirect('/login');
});

// ── Audit ────────────────────────────────────────────────────────────

test('credential download is logged in audit', function () {
    $credentials = [
        ['name' => 'Siswa Test', 'username' => '50001', 'password' => 'testpw'],
    ];

    $key = 'audit-test-key';
    Cache::put("credentials:{$key}", $credentials, 1800);

    $this->actingAs($this->admin)
        ->get(route('admin.credentials.pdf', $key));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'credential_download',
        'user_id' => $this->admin->id,
    ]);
});

// ── Multiple downloads allowed ───────────────────────────────────────

test('credentials can be downloaded multiple times before expiry', function () {
    $credentials = [
        ['name' => 'Multi Test', 'username' => '60001', 'password' => 'multi123'],
    ];

    $key = 'multi-download-key';
    Cache::put("credentials:{$key}", $credentials, 1800);

    // First download
    $response1 = $this->actingAs($this->admin)
        ->get(route('admin.credentials.pdf', $key));
    $response1->assertOk();

    // Second download
    $response2 = $this->actingAs($this->admin)
        ->get(route('admin.credentials.excel', $key));
    $response2->assertOk();

    // Third download
    $response3 = $this->actingAs($this->admin)
        ->get(route('admin.credentials.pdf', $key));
    $response3->assertOk();
});
