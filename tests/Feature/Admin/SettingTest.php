<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    // Seed default settings
    $this->seed(\Database\Seeders\SettingSeeder::class);

    // Clear cache to prevent stale data from previous tests
    app(SettingService::class)->clearCache();
});

// ── Index ────────────────────────────────────────────────────────────

test('admin can view settings page', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.settings.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Settings/Index')
        ->has('settings.general')
        ->has('settings.appearance')
        ->has('settings.exam')
        ->has('settings.email')
    );
});

test('settings page masks smtp password', function () {
    Setting::where('key', 'smtp_password')->update(['value' => 'secret123']);
    app(SettingService::class)->clearCache();

    $response = $this->actingAs($this->admin)->get(route('admin.settings.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('settings.email.smtp_password', '********')
    );
});

test('non-admin cannot access settings', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('admin.settings.index'));
    $response->assertForbidden();
});

// ── Update General ───────────────────────────────────────────────────

test('admin can update general settings', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-general'), [
        'app_name' => 'My LMS',
        'school_name' => 'SMK Hebat',
        'school_address' => 'Jl. Pendidikan 1',
        'school_phone' => '021-123456',
        'school_email' => 'info@smkhebat.sch.id',
        'school_website' => 'https://smkhebat.sch.id',
        'school_tagline' => 'Hebat dan Berkarakter',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    app(SettingService::class)->clearCache();

    expect(setting('app_name'))->toBe('My LMS');
    expect(setting('school_name'))->toBe('SMK Hebat');
    expect(setting('school_address'))->toBe('Jl. Pendidikan 1');
});

test('general settings validates required fields', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-general'), [
        'app_name' => '',
        'school_name' => '',
    ]);

    $response->assertSessionHasErrors(['app_name', 'school_name']);
});

// ── Update Appearance ────────────────────────────────────────────────

test('admin can update appearance settings', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-appearance'), [
        'primary_color' => '#ff0000',
        'secondary_color' => '#00ff00',
        'login_bg_type' => 'color',
        'login_bg_value' => '#ffffff',
        'footer_text' => '© 2026 Test',
        'show_powered_by' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    app(SettingService::class)->clearCache();

    expect(setting('primary_color'))->toBe('#ff0000');
    expect(setting('show_powered_by'))->toBeFalse();
});

test('admin can upload logo', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    $response = $this->actingAs($this->admin)->call('PUT', route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'color',
        'show_powered_by' => true,
    ], [], ['logo' => $file]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    app(SettingService::class)->clearCache();

    $logoPath = setting('logo_path');
    expect($logoPath)->toStartWith('settings/');
    Storage::disk('public')->assertExists($logoPath);
});

test('appearance validates hex color format', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-appearance'), [
        'primary_color' => 'not-a-color',
        'secondary_color' => '#zzzzzz',
        'login_bg_type' => 'color',
        'show_powered_by' => true,
    ]);

    $response->assertSessionHasErrors(['primary_color', 'secondary_color']);
});

// ── Update Exam ──────────────────────────────────────────────────────

test('admin can update exam settings', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-exam'), [
        'default_duration_minutes' => 90,
        'auto_submit_on_timeout' => false,
        'show_result_after_submit' => true,
        'anti_cheat_enabled' => false,
        'max_tab_switches_default' => 5,
        'allow_mobile_exam' => true,
        'device_lock_default' => false,
        'watermark_enabled' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    app(SettingService::class)->clearCache();

    expect(setting('default_duration_minutes'))->toBe(90);
    expect(setting('auto_submit_on_timeout'))->toBeFalse();
    expect(setting('allow_mobile_exam'))->toBeTrue();
});

test('exam settings validates duration range', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-exam'), [
        'default_duration_minutes' => 500,
        'auto_submit_on_timeout' => true,
        'show_result_after_submit' => false,
        'anti_cheat_enabled' => true,
        'max_tab_switches_default' => 3,
        'allow_mobile_exam' => false,
        'device_lock_default' => true,
        'watermark_enabled' => true,
    ]);

    $response->assertSessionHasErrors('default_duration_minutes');
});

// ── Update Email ─────────────────────────────────────────────────────

test('admin can update email settings', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 465,
        'smtp_username' => 'test@gmail.com',
        'smtp_password' => 'newpassword',
        'smtp_encryption' => 'ssl',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    app(SettingService::class)->clearCache();

    expect(setting('smtp_host'))->toBe('smtp.gmail.com');
    expect(setting('smtp_port'))->toBe(465);
    expect(setting('smtp_encryption'))->toBe('ssl');
});

test('email settings does not overwrite password with mask', function () {
    Setting::where('key', 'smtp_password')->update(['value' => 'real_password']);
    app(SettingService::class)->clearCache();

    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => 587,
        'smtp_username' => 'user',
        'smtp_password' => '********',
        'smtp_encryption' => 'tls',
    ]);

    $response->assertRedirect();

    app(SettingService::class)->clearCache();

    expect(setting('smtp_password'))->toBe('real_password');
});

// ── Helper & Cache ───────────────────────────────────────────────────

test('setting helper returns value from database', function () {
    app(SettingService::class)->clearCache();

    expect(setting('app_name'))->toBe('SMK LMS');
    expect(setting('school_name'))->toBe('SMK Bina Mandiri');
});

test('setting helper returns default when key not found', function () {
    expect(setting('nonexistent_key', 'fallback'))->toBe('fallback');
});

test('cache is invalidated on update', function () {
    app(SettingService::class)->clearCache();

    // First access populates cache
    expect(setting('app_name'))->toBe('SMK LMS');

    // Update through service
    app(SettingService::class)->set('app_name', 'Updated Name');

    // Cache should be cleared, next access returns new value
    expect(setting('app_name'))->toBe('Updated Name');
});

// ── Seeder idempotency ───────────────────────────────────────────────

test('setting seeder is idempotent', function () {
    $countBefore = Setting::count();

    // Run seeder again
    $this->seed(\Database\Seeders\SettingSeeder::class);

    expect(Setting::count())->toBe($countBefore);
});

// ── Shared settings in Inertia ───────────────────────────────────────

test('app_settings shared to frontend', function () {
    app(SettingService::class)->clearCache();

    $response = $this->actingAs($this->admin)->get(route('admin.settings.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('app_settings.app_name')
        ->has('app_settings.school_name')
        ->has('app_settings.logo_path')
    );
});

// ── Authorization on all endpoints ───────────────────────────────────

test('guru cannot access any settings endpoint', function () {
    $guru = User::factory()->guru()->create();

    $this->actingAs($guru)->get(route('admin.settings.index'))->assertForbidden();
    $this->actingAs($guru)->put(route('admin.settings.update-general'), [])->assertForbidden();
    $this->actingAs($guru)->put(route('admin.settings.update-appearance'), [])->assertForbidden();
    $this->actingAs($guru)->put(route('admin.settings.update-exam'), [])->assertForbidden();
    $this->actingAs($guru)->put(route('admin.settings.update-email'), [])->assertForbidden();
});

test('siswa cannot access any settings endpoint', function () {
    $siswa = User::factory()->siswa()->create();

    $this->actingAs($siswa)->get(route('admin.settings.index'))->assertForbidden();
    $this->actingAs($siswa)->put(route('admin.settings.update-general'), [])->assertForbidden();
    $this->actingAs($siswa)->put(route('admin.settings.update-appearance'), [])->assertForbidden();
    $this->actingAs($siswa)->put(route('admin.settings.update-exam'), [])->assertForbidden();
    $this->actingAs($siswa)->put(route('admin.settings.update-email'), [])->assertForbidden();
});

test('unauthenticated user is redirected from settings', function () {
    $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
});

// ── General settings edge cases ──────────────────────────────────────

test('general settings allows nullable fields to be empty', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-general'), [
        'app_name' => 'Test App',
        'school_name' => 'Test School',
        'school_address' => '',
        'school_phone' => '',
        'school_email' => '',
        'school_website' => '',
        'school_tagline' => '',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('general settings validates max length', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-general'), [
        'app_name' => str_repeat('a', 101),
        'school_name' => str_repeat('b', 256),
    ]);

    $response->assertSessionHasErrors(['app_name', 'school_name']);
});

test('general settings validates email format', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-general'), [
        'app_name' => 'Test',
        'school_name' => 'Test',
        'school_email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('school_email');
});

test('general settings validates url format', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-general'), [
        'app_name' => 'Test',
        'school_name' => 'Test',
        'school_website' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('school_website');
});

test('general settings persists all fields correctly', function () {
    $data = [
        'app_name' => 'Updated App',
        'school_name' => 'Updated School',
        'school_address' => 'Jl. Test 123',
        'school_phone' => '08123456789',
        'school_email' => 'test@school.id',
        'school_website' => 'https://school.id',
        'school_tagline' => 'Test Tagline',
    ];

    $this->actingAs($this->admin)->put(route('admin.settings.update-general'), $data);

    app(SettingService::class)->clearCache();

    foreach ($data as $key => $value) {
        expect(setting($key))->toBe($value, "Setting '{$key}' should be '{$value}'");
    }
});

// ── Appearance edge cases ────────────────────────────────────────────

test('admin can upload logo_small', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('favicon.png', 32, 32);

    $response = $this->actingAs($this->admin)->call('PUT', route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'color',
        'show_powered_by' => true,
    ], [], ['logo_small' => $file]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    app(SettingService::class)->clearCache();

    $logoSmallPath = setting('logo_small_path');
    expect($logoSmallPath)->toStartWith('settings/');
    Storage::disk('public')->assertExists($logoSmallPath);
});

test('logo upload replaces old file', function () {
    Storage::fake('public');

    // Upload first logo
    $file1 = UploadedFile::fake()->image('logo1.png', 200, 200);
    $this->actingAs($this->admin)->call('PUT', route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'color',
        'show_powered_by' => true,
    ], [], ['logo' => $file1]);

    app(SettingService::class)->clearCache();
    $oldPath = setting('logo_path');

    // Upload second logo
    $file2 = UploadedFile::fake()->image('logo2.png', 300, 300);
    $this->actingAs($this->admin)->call('PUT', route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'color',
        'show_powered_by' => true,
    ], [], ['logo' => $file2]);

    app(SettingService::class)->clearCache();
    $newPath = setting('logo_path');

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing($oldPath);
});

test('appearance validates login_bg_type values', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'invalid',
        'show_powered_by' => true,
    ]);

    $response->assertSessionHasErrors('login_bg_type');
});

test('appearance rejects oversized logo', function () {
    Storage::fake('public');

    // Create file > 2MB
    $file = UploadedFile::fake()->image('big.png')->size(3000);

    $response = $this->actingAs($this->admin)->call('PUT', route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'color',
        'show_powered_by' => true,
    ], [], ['logo' => $file]);

    $response->assertSessionHasErrors('logo');
});

test('appearance show_powered_by boolean persists correctly', function () {
    // Set to false
    $this->actingAs($this->admin)->put(route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'color',
        'show_powered_by' => false,
    ]);

    app(SettingService::class)->clearCache();
    expect(setting('show_powered_by'))->toBeFalse();

    // Set back to true
    $this->actingAs($this->admin)->put(route('admin.settings.update-appearance'), [
        'primary_color' => '#2563eb',
        'secondary_color' => '#64748b',
        'login_bg_type' => 'color',
        'show_powered_by' => true,
    ]);

    app(SettingService::class)->clearCache();
    expect(setting('show_powered_by'))->toBeTrue();
});

// ── Exam settings edge cases ─────────────────────────────────────────

test('exam settings validates duration minimum', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-exam'), [
        'default_duration_minutes' => 0,
        'auto_submit_on_timeout' => true,
        'show_result_after_submit' => false,
        'anti_cheat_enabled' => true,
        'max_tab_switches_default' => 3,
        'allow_mobile_exam' => false,
        'device_lock_default' => true,
        'watermark_enabled' => true,
    ]);

    $response->assertSessionHasErrors('default_duration_minutes');
});

test('exam settings validates max tab switches range', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-exam'), [
        'default_duration_minutes' => 60,
        'auto_submit_on_timeout' => true,
        'show_result_after_submit' => false,
        'anti_cheat_enabled' => true,
        'max_tab_switches_default' => 100,
        'allow_mobile_exam' => false,
        'device_lock_default' => true,
        'watermark_enabled' => true,
    ]);

    $response->assertSessionHasErrors('max_tab_switches_default');
});

test('exam settings persists all boolean fields correctly', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update-exam'), [
        'default_duration_minutes' => 120,
        'auto_submit_on_timeout' => false,
        'show_result_after_submit' => true,
        'anti_cheat_enabled' => false,
        'max_tab_switches_default' => 10,
        'allow_mobile_exam' => true,
        'device_lock_default' => false,
        'watermark_enabled' => false,
    ]);

    app(SettingService::class)->clearCache();

    expect(setting('default_duration_minutes'))->toBe(120);
    expect(setting('auto_submit_on_timeout'))->toBeFalse();
    expect(setting('show_result_after_submit'))->toBeTrue();
    expect(setting('anti_cheat_enabled'))->toBeFalse();
    expect(setting('max_tab_switches_default'))->toBe(10);
    expect(setting('allow_mobile_exam'))->toBeTrue();
    expect(setting('device_lock_default'))->toBeFalse();
    expect(setting('watermark_enabled'))->toBeFalse();
});

test('exam settings boundary values accepted', function () {
    // Min values
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-exam'), [
        'default_duration_minutes' => 1,
        'auto_submit_on_timeout' => true,
        'show_result_after_submit' => false,
        'anti_cheat_enabled' => true,
        'max_tab_switches_default' => 1,
        'allow_mobile_exam' => false,
        'device_lock_default' => true,
        'watermark_enabled' => true,
    ]);
    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Max values
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-exam'), [
        'default_duration_minutes' => 480,
        'auto_submit_on_timeout' => true,
        'show_result_after_submit' => false,
        'anti_cheat_enabled' => true,
        'max_tab_switches_default' => 99,
        'allow_mobile_exam' => false,
        'device_lock_default' => true,
        'watermark_enabled' => true,
    ]);
    $response->assertRedirect();
    $response->assertSessionHas('success');
});

// ── Email settings edge cases ────────────────────────────────────────

test('email settings validates encryption values', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => 587,
        'smtp_username' => 'user',
        'smtp_password' => 'pass',
        'smtp_encryption' => 'invalid',
    ]);

    $response->assertSessionHasErrors('smtp_encryption');
});

test('email settings validates port range', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => 70000,
        'smtp_username' => 'user',
        'smtp_password' => 'pass',
        'smtp_encryption' => 'tls',
    ]);

    $response->assertSessionHasErrors('smtp_port');
});

test('email settings accepts all valid encryption types', function () {
    foreach (['none', 'tls', 'ssl'] as $encryption) {
        $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
            'smtp_host' => 'smtp.test.com',
            'smtp_port' => 587,
            'smtp_username' => 'user',
            'smtp_password' => '********',
            'smtp_encryption' => $encryption,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        app(SettingService::class)->clearCache();
        expect(setting('smtp_encryption'))->toBe($encryption);
    }
});

test('email settings with empty password does not overwrite existing', function () {
    Setting::where('key', 'smtp_password')->update(['value' => 'my_secret']);
    app(SettingService::class)->clearCache();

    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => 587,
        'smtp_username' => 'user',
        'smtp_password' => null,
        'smtp_encryption' => 'tls',
    ]);

    $response->assertRedirect();

    app(SettingService::class)->clearCache();
    expect(setting('smtp_password'))->toBe('my_secret');
});

test('email settings with actual new password does overwrite', function () {
    Setting::where('key', 'smtp_password')->update(['value' => 'old_password']);
    app(SettingService::class)->clearCache();

    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => 587,
        'smtp_username' => 'user',
        'smtp_password' => 'new_real_password',
        'smtp_encryption' => 'tls',
    ]);

    $response->assertRedirect();

    app(SettingService::class)->clearCache();
    expect(setting('smtp_password'))->toBe('new_real_password');
});

test('email settings persists all fields correctly', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.update-email'), [
        'smtp_host' => 'mail.example.com',
        'smtp_port' => 465,
        'smtp_username' => 'admin@example.com',
        'smtp_password' => 'secretpass',
        'smtp_encryption' => 'ssl',
    ]);

    $response->assertRedirect();
    app(SettingService::class)->clearCache();

    expect(setting('smtp_host'))->toBe('mail.example.com');
    expect(setting('smtp_port'))->toBe(465);
    expect(setting('smtp_username'))->toBe('admin@example.com');
    expect(setting('smtp_password'))->toBe('secretpass');
    expect(setting('smtp_encryption'))->toBe('ssl');
});

// ── Index returns correct data structure ─────────────────────────────

test('settings page returns all expected keys per group', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.settings.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Settings/Index')
        // General group
        ->has('settings.general.app_name')
        ->has('settings.general.school_name')
        ->has('settings.general.school_address')
        ->has('settings.general.school_phone')
        ->has('settings.general.school_email')
        ->has('settings.general.school_website')
        ->has('settings.general.school_tagline')
        // Appearance group
        ->has('settings.appearance.logo_path')
        ->has('settings.appearance.primary_color')
        ->has('settings.appearance.secondary_color')
        ->has('settings.appearance.login_bg_type')
        ->has('settings.appearance.footer_text')
        ->has('settings.appearance.show_powered_by')
        // Exam group
        ->has('settings.exam.default_duration_minutes')
        ->has('settings.exam.auto_submit_on_timeout')
        ->has('settings.exam.show_result_after_submit')
        ->has('settings.exam.anti_cheat_enabled')
        ->has('settings.exam.max_tab_switches_default')
        ->has('settings.exam.allow_mobile_exam')
        ->has('settings.exam.device_lock_default')
        ->has('settings.exam.watermark_enabled')
        // Email group
        ->has('settings.email.smtp_host')
        ->has('settings.email.smtp_port')
        ->has('settings.email.smtp_username')
        ->has('settings.email.smtp_password')
        ->has('settings.email.smtp_encryption')
    );
});

test('settings page returns empty smtp password as non-masked', function () {
    // Default seeder sets smtp_password to empty string
    $response = $this->actingAs($this->admin)->get(route('admin.settings.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('settings.email.smtp_password', '')
    );
});
