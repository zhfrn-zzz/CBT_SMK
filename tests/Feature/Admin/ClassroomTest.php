<?php

declare(strict_types=1);

use App\Enums\GradeLevel;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
});

// ── Index ────────────────────────────────────────────────────────────

test('admin can view classrooms index', function () {
    Classroom::factory()->count(3)->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.classrooms.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Classrooms/Index')
        ->has('classrooms.data', 3)
    );
});

test('admin can filter classrooms by academic year', function () {
    $otherYear = AcademicYear::factory()->create(['is_active' => false]);

    Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    Classroom::factory()->create([
        'academic_year_id' => $otherYear->id,
        'department_id' => $this->department->id,
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.classrooms.index', [
        'academic_year_id' => $this->academicYear->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Classrooms/Index')
        ->has('classrooms.data', 1)
    );
});

// ── Create / Store ──────────────────────────────────────────────────

test('admin can view create classroom form', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.classrooms.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Admin/Classrooms/Create'));
});

test('admin can create a classroom', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.classrooms.store'), [
        'name' => 'X TKJ 1',
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
        'grade_level' => GradeLevel::X->value,
    ]);

    $response->assertRedirect(route('admin.classrooms.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('classrooms', [
        'name' => 'X TKJ 1',
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
        'grade_level' => GradeLevel::X->value,
    ]);
});

// ── Validation ──────────────────────────────────────────────────────

test('create classroom requires name', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.classrooms.store'), [
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
        'grade_level' => GradeLevel::X->value,
    ]);

    $response->assertSessionHasErrors('name');
});

test('create classroom requires valid academic year', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.classrooms.store'), [
        'name' => 'X TKJ 1',
        'academic_year_id' => 99999,
        'department_id' => $this->department->id,
        'grade_level' => GradeLevel::X->value,
    ]);

    $response->assertSessionHasErrors('academic_year_id');
});

test('create classroom requires valid grade level', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.classrooms.store'), [
        'name' => 'X TKJ 1',
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
        'grade_level' => '13',
    ]);

    $response->assertSessionHasErrors('grade_level');
});

// ── Show ────────────────────────────────────────────────────────────

test('admin can view classroom detail with students', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $students = User::factory()->siswa()->count(3)->create();
    $classroom->students()->attach($students->pluck('id'));

    $response = $this->actingAs($this->admin)->get(route('admin.classrooms.show', $classroom));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Classrooms/Show')
        ->has('classroom')
        ->has('availableStudents')
        ->has('teachingAssignments')
    );
});

// ── Edit / Update ───────────────────────────────────────────────────

test('admin can update classroom', function () {
    $classroom = Classroom::factory()->create([
        'name' => 'Old Name',
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.classrooms.update', $classroom), [
        'name' => 'New Name',
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
        'grade_level' => GradeLevel::XI->value,
    ]);

    $response->assertRedirect(route('admin.classrooms.index'));
    $response->assertSessionHas('success');

    $classroom->refresh();
    expect($classroom->name)->toBe('New Name');
});

// ── Delete ──────────────────────────────────────────────────────────

test('admin can delete classroom', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);

    $response = $this->actingAs($this->admin)->delete(route('admin.classrooms.destroy', $classroom));

    $response->assertRedirect(route('admin.classrooms.index'));

    $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
});

// ── Assign Students ─────────────────────────────────────────────────

test('admin can assign students to classroom', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $students = User::factory()->siswa()->count(3)->create();

    $response = $this->actingAs($this->admin)->post(
        route('admin.classrooms.assign-students', $classroom),
        ['student_ids' => $students->pluck('id')->toArray()]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($classroom->students()->count())->toBe(3);
});

test('assign students is idempotent (syncWithoutDetaching)', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $student = User::factory()->siswa()->create();
    $classroom->students()->attach($student->id);

    $response = $this->actingAs($this->admin)->post(
        route('admin.classrooms.assign-students', $classroom),
        ['student_ids' => [$student->id]]
    );

    $response->assertRedirect();
    expect($classroom->students()->count())->toBe(1);
});

// ── Remove Student ──────────────────────────────────────────────────

test('admin can remove student from classroom', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $student = User::factory()->siswa()->create();
    $classroom->students()->attach($student->id);

    $response = $this->actingAs($this->admin)->delete(
        route('admin.classrooms.remove-student', [$classroom, $student])
    );

    $response->assertRedirect();

    expect($classroom->students()->count())->toBe(0);
});

// ── Assign Teacher ──────────────────────────────────────────────────

test('admin can assign teacher to classroom with subject', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $guru = User::factory()->guru()->create();
    $subject = Subject::factory()->create();

    $response = $this->actingAs($this->admin)->post(
        route('admin.classrooms.assign-teacher', $classroom),
        ['user_id' => $guru->id, 'subject_id' => $subject->id]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('classroom_subject_teacher', [
        'classroom_id' => $classroom->id,
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);
});

test('duplicate teacher assignment is rejected', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $guru = User::factory()->guru()->create();
    $subject = Subject::factory()->create();

    DB::table('classroom_subject_teacher')->insert([
        'classroom_id' => $classroom->id,
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    $response = $this->actingAs($this->admin)->post(
        route('admin.classrooms.assign-teacher', $classroom),
        ['user_id' => $guru->id, 'subject_id' => $subject->id]
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// ── Remove Teacher ──────────────────────────────────────────────────

test('admin can remove teacher assignment', function () {
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $guru = User::factory()->guru()->create();
    $subject = Subject::factory()->create();

    $assignmentId = DB::table('classroom_subject_teacher')->insertGetId([
        'classroom_id' => $classroom->id,
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    $response = $this->actingAs($this->admin)->delete(
        route('admin.classrooms.remove-teacher', [$classroom, $assignmentId])
    );

    $response->assertRedirect();

    $this->assertDatabaseMissing('classroom_subject_teacher', [
        'id' => $assignmentId,
    ]);
});

// ── Role access ─────────────────────────────────────────────────────

test('guru cannot access classroom management', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('admin.classrooms.index'));

    $response->assertForbidden();
});
