<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ── Index ────────────────────────────────────────────────────────────

test('admin can view departments index', function () {
    Department::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.departments.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Departments/Index')
        ->has('departments.data', 3)
    );
});

// ── Create / Store ──────────────────────────────────────────────────

test('admin can view create department form', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.departments.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Admin/Departments/Create'));
});

test('admin can create a department', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
        'name' => 'Teknik Komputer Jaringan',
        'code' => 'TKJ',
    ]);

    $response->assertRedirect(route('admin.departments.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('departments', [
        'name' => 'Teknik Komputer Jaringan',
        'code' => 'TKJ',
    ]);
});

// ── Validation ──────────────────────────────────────────────────────

test('create department requires name', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
        'code' => 'TKJ',
    ]);

    $response->assertSessionHasErrors('name');
});

test('create department requires code', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
        'name' => 'Teknik Komputer Jaringan',
    ]);

    $response->assertSessionHasErrors('code');
});

test('department code must be unique', function () {
    Department::factory()->create(['code' => 'TKJ']);

    $response = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
        'name' => 'Another Department',
        'code' => 'TKJ',
    ]);

    $response->assertSessionHasErrors('code');
});

test('department code max 20 characters', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.departments.store'), [
        'name' => 'Test',
        'code' => str_repeat('A', 21),
    ]);

    $response->assertSessionHasErrors('code');
});

// ── Edit / Update ───────────────────────────────────────────────────

test('admin can view edit department form', function () {
    $dept = Department::factory()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.departments.edit', $dept));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Departments/Edit')
        ->has('department')
    );
});

test('admin can update department', function () {
    $dept = Department::factory()->create(['name' => 'Old']);

    $response = $this->actingAs($this->admin)->put(route('admin.departments.update', $dept), [
        'name' => 'Updated Name',
        'code' => $dept->code,
    ]);

    $response->assertRedirect(route('admin.departments.index'));
    $response->assertSessionHas('success');

    $dept->refresh();
    expect($dept->name)->toBe('Updated Name');
});

test('update department code unique ignores self', function () {
    $dept = Department::factory()->create(['code' => 'TKJ']);

    $response = $this->actingAs($this->admin)->put(route('admin.departments.update', $dept), [
        'name' => $dept->name,
        'code' => 'TKJ',
    ]);

    $response->assertRedirect(route('admin.departments.index'));
});

// ── Delete ──────────────────────────────────────────────────────────

test('admin can delete department', function () {
    $dept = Department::factory()->create();

    $response = $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $dept));

    $response->assertRedirect(route('admin.departments.index'));

    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

// ── Role access ─────────────────────────────────────────────────────

test('guru cannot access departments', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('admin.departments.index'));

    $response->assertForbidden();
});
