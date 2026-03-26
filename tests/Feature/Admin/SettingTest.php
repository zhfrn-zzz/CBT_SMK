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
