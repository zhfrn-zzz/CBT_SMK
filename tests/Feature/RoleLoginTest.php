<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

test('admin can login with username and is redirected to admin dashboard', function () {
    $admin = User::factory()->admin()->create([
        'username' => 'adminuser',
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'adminuser',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect('/admin/dashboard');
});

test('guru can login with username and is redirected to guru dashboard', function () {
    $guru = User::factory()->guru()->create([
        'username' => '198501012010011001',
    ]);

    $response = $this->post(route('login.store'), [
        'username' => '198501012010011001',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($guru);
    $response->assertRedirect('/guru/dashboard');
});

test('siswa can login with username and is redirected to siswa dashboard', function () {
    $siswa = User::factory()->siswa()->create([
        'username' => '10001',
    ]);

    $response = $this->post(route('login.store'), [
        'username' => '10001',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($siswa);
    $response->assertRedirect('/siswa/dashboard');
});

test('dashboard redirects admin to admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertRedirect('/admin/dashboard');
});

test('dashboard redirects guru to guru dashboard', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertRedirect('/guru/dashboard');
});

test('dashboard redirects siswa to siswa dashboard', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get(route('dashboard'));

    $response->assertRedirect('/siswa/dashboard');
});

test('inactive user is logged out on role-protected route', function () {
    $guru = User::factory()->guru()->create(['is_active' => false]);

    $response = $this->actingAs($guru)->get('/guru/dashboard');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('siswa cannot access admin routes', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get('/admin/dashboard');

    $response->assertForbidden();
});

test('siswa cannot access guru routes', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get('/guru/dashboard');

    $response->assertForbidden();
});

test('guru cannot access admin routes', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get('/admin/dashboard');

    $response->assertForbidden();
});

test('guru cannot access siswa routes', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get('/siswa/dashboard');

    $response->assertForbidden();
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect(route('login'));
});
