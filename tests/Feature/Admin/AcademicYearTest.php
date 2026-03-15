<?php

declare(strict_types=1);

use App\Enums\Semester;
use App\Models\AcademicYear;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ── Index ────────────────────────────────────────────────────────────

test('admin can view academic years index', function () {
    AcademicYear::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.academic-years.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/AcademicYears/Index')
        ->has('academicYears.data', 3)
    );
});

// ── Create / Store ──────────────────────────────────────────────────

test('admin can view create academic year form', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.academic-years.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/AcademicYears/Create')
        ->has('semesters')
    );
});

test('admin can create an academic year', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.academic-years.store'), [
        'name' => '2025/2026',
        'semester' => Semester::Ganjil->value,
        'is_active' => false,
        'starts_at' => '2025-07-01',
        'ends_at' => '2026-06-30',
    ]);

    $response->assertRedirect(route('admin.academic-years.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('academic_years', [
        'name' => '2025/2026',
        'semester' => Semester::Ganjil->value,
    ]);
});

test('creating active academic year deactivates others', function () {
    $existing = AcademicYear::factory()->active()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.academic-years.store'), [
        'name' => '2026/2027',
        'semester' => Semester::Ganjil->value,
        'is_active' => true,
        'starts_at' => '2026-07-01',
        'ends_at' => '2027-06-30',
    ]);

    $response->assertRedirect(route('admin.academic-years.index'));

    $existing->refresh();
    expect($existing->is_active)->toBeFalse();

    $this->assertDatabaseHas('academic_years', [
        'name' => '2026/2027',
        'is_active' => true,
    ]);
});

// ── Validation ──────────────────────────────────────────────────────

test('create academic year requires name', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.academic-years.store'), [
        'semester' => Semester::Ganjil->value,
        'starts_at' => '2025-07-01',
        'ends_at' => '2026-06-30',
    ]);

    $response->assertSessionHasErrors('name');
});

test('create academic year requires valid semester', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.academic-years.store'), [
        'name' => '2025/2026',
        'semester' => 'invalid',
        'starts_at' => '2025-07-01',
        'ends_at' => '2026-06-30',
    ]);

    $response->assertSessionHasErrors('semester');
});

test('ends_at must be after starts_at', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.academic-years.store'), [
        'name' => '2025/2026',
        'semester' => Semester::Ganjil->value,
        'starts_at' => '2026-07-01',
        'ends_at' => '2025-06-30',
    ]);

    $response->assertSessionHasErrors('ends_at');
});

// ── Edit / Update ───────────────────────────────────────────────────

test('admin can view edit academic year form', function () {
    $year = AcademicYear::factory()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.academic-years.edit', $year));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/AcademicYears/Edit')
        ->has('academicYear')
        ->has('semesters')
    );
});

test('admin can update academic year', function () {
    $year = AcademicYear::factory()->create(['name' => 'Old']);

    $response = $this->actingAs($this->admin)->put(route('admin.academic-years.update', $year), [
        'name' => 'Updated Name',
        'semester' => $year->semester->value,
        'is_active' => false,
        'starts_at' => $year->starts_at->format('Y-m-d'),
        'ends_at' => $year->ends_at->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('admin.academic-years.index'));
    $response->assertSessionHas('success');

    $year->refresh();
    expect($year->name)->toBe('Updated Name');
});

test('updating to active deactivates other academic years', function () {
    $active = AcademicYear::factory()->active()->create();
    $inactive = AcademicYear::factory()->create(['is_active' => false]);

    $response = $this->actingAs($this->admin)->put(route('admin.academic-years.update', $inactive), [
        'name' => $inactive->name,
        'semester' => $inactive->semester->value,
        'is_active' => true,
        'starts_at' => $inactive->starts_at->format('Y-m-d'),
        'ends_at' => $inactive->ends_at->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('admin.academic-years.index'));

    $active->refresh();
    $inactive->refresh();
    expect($active->is_active)->toBeFalse();
    expect($inactive->is_active)->toBeTrue();
});

// ── Delete ──────────────────────────────────────────────────────────

test('admin can delete academic year', function () {
    $year = AcademicYear::factory()->create();

    $response = $this->actingAs($this->admin)->delete(route('admin.academic-years.destroy', $year));

    $response->assertRedirect(route('admin.academic-years.index'));

    $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
});

// ── Role access ─────────────────────────────────────────────────────

test('guru cannot access academic years', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('admin.academic-years.index'));

    $response->assertForbidden();
});
