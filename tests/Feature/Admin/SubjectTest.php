<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ── Index ────────────────────────────────────────────────────────────

test('admin can view subjects index', function () {
    Subject::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.subjects.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Subjects/Index')
        ->has('subjects.data', 3)
    );
});

// ── Create / Store ──────────────────────────────────────────────────

test('admin can view create subject form', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.subjects.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Subjects/Create')
        ->has('departments')
    );
});

test('admin can create a subject', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.subjects.store'), [
        'name' => 'Matematika',
        'code' => 'MTK',
    ]);

    $response->assertRedirect(route('admin.subjects.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('subjects', [
        'name' => 'Matematika',
        'code' => 'MTK',
    ]);
});

test('admin can create subject with department', function () {
    $dept = Department::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.subjects.store'), [
        'name' => 'Pemrograman Web',
        'code' => 'PW',
        'department_id' => $dept->id,
    ]);

    $response->assertRedirect(route('admin.subjects.index'));

    $this->assertDatabaseHas('subjects', [
        'name' => 'Pemrograman Web',
        'department_id' => $dept->id,
    ]);
});

// ── Validation ──────────────────────────────────────────────────────

test('create subject requires name', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.subjects.store'), [
        'code' => 'MTK',
    ]);

    $response->assertSessionHasErrors('name');
});

test('create subject requires code', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.subjects.store'), [
        'name' => 'Matematika',
    ]);

    $response->assertSessionHasErrors('code');
});

test('subject code must be unique', function () {
    Subject::factory()->create(['code' => 'MTK']);

    $response = $this->actingAs($this->admin)->post(route('admin.subjects.store'), [
        'name' => 'Another Subject',
        'code' => 'MTK',
    ]);

    $response->assertSessionHasErrors('code');
});

// ── Edit / Update ───────────────────────────────────────────────────

test('admin can view edit subject form', function () {
    $subject = Subject::factory()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.subjects.edit', $subject));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Subjects/Edit')
        ->has('subject')
        ->has('departments')
    );
});

test('admin can update subject', function () {
    $subject = Subject::factory()->create(['name' => 'Old']);

    $response = $this->actingAs($this->admin)->put(route('admin.subjects.update', $subject), [
        'name' => 'Updated Subject',
        'code' => $subject->code,
    ]);

    $response->assertRedirect(route('admin.subjects.index'));
    $response->assertSessionHas('success');

    $subject->refresh();
    expect($subject->name)->toBe('Updated Subject');
});

test('update subject code unique ignores self', function () {
    $subject = Subject::factory()->create(['code' => 'MTK']);

    $response = $this->actingAs($this->admin)->put(route('admin.subjects.update', $subject), [
        'name' => $subject->name,
        'code' => 'MTK',
    ]);

    $response->assertRedirect(route('admin.subjects.index'));
});

// ── Delete ──────────────────────────────────────────────────────────

test('admin can delete subject', function () {
    $subject = Subject::factory()->create();

    $response = $this->actingAs($this->admin)->delete(route('admin.subjects.destroy', $subject));

    $response->assertRedirect(route('admin.subjects.index'));

    $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
});

// ── Role access ─────────────────────────────────────────────────────

test('guru cannot access subjects management', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('admin.subjects.index'));

    $response->assertForbidden();
});
