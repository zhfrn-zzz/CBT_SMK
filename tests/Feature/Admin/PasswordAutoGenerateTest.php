<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ── Auto-Generate Password on Store ──────────────────────────────────

test('siswa created without password gets auto-generated password', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Siswa Auto',
        'username' => '20001',
        'role' => UserRole::Siswa->value,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('generated_password');
    $response->assertSessionHas('generated_user_name', 'Siswa Auto');
    $response->assertSessionHas('generated_user_username', '20001');

    $user = User::where('username', '20001')->first();
    expect($user)->not->toBeNull();

    // Verify the generated password works
    $password = session('generated_password');
    expect(Hash::check($password, $user->password))->toBeTrue();
});

test('siswa created with explicit password uses provided password', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Siswa Manual',
        'username' => '20002',
        'role' => UserRole::Siswa->value,
        'password' => 'ManualPass1!',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionMissing('generated_password');

    $user = User::where('username', '20002')->first();
    expect(Hash::check('ManualPass1!', $user->password))->toBeTrue();
});

test('guru requires password on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Guru Test',
        'username' => '198500001',
        'role' => UserRole::Guru->value,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('password');
});

// ── Reset Password ───────────────────────────────────────────────────

test('admin can reset siswa password', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.users.reset-password', $siswa));

    $response->assertOk();
    $response->assertJsonStructure(['password', 'name', 'username']);

    $newPassword = $response->json('password');
    expect(strlen($newPassword))->toBe(8);

    $siswa->refresh();
    expect(Hash::check($newPassword, $siswa->password))->toBeTrue();
});

test('reset password creates audit log', function () {
    $siswa = User::factory()->siswa()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.users.reset-password', $siswa));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'password_reset',
        'auditable_type' => User::class,
        'auditable_id' => $siswa->id,
        'user_id' => $this->admin->id,
    ]);
});

test('cannot reset password for non-siswa', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.users.reset-password', $guru));

    $response->assertForbidden();
});

test('non-admin cannot reset password', function () {
    $guru = User::factory()->guru()->create();
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($guru)
        ->postJson(route('admin.users.reset-password', $siswa));

    $response->assertForbidden();
});

// ── Import stores credentials in cache ──────────────────────────────

test('import stores credentials in cache and flashes key', function () {
    $academicYear = \App\Models\AcademicYear::factory()->active()->create();

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
        'students.csv',
        "nis,nama,email,password\n30001,Siswa Import 1,,\n30002,Siswa Import 2,,\n"
    );

    $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
        'file' => $file,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('credential_key');
    $response->assertSessionHas('credential_count', 2);

    $key = session('credential_key');
    $credentials = Cache::get("credentials:{$key}");

    expect($credentials)->toHaveCount(2);
    expect($credentials[0])->toHaveKeys(['name', 'username', 'password']);
    expect($credentials[0]['username'])->toBe('30001');
});
