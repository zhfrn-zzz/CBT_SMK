<?php

declare(strict_types=1);

use App\Enums\ExamStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);
    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);
    $this->createPgQuestions($this->questionBank, 5);
});

// ===== Create =====

test('guru can view create exam session form', function () {
    $response = $this->actingAs($this->guru)->get(route('guru.ujian.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Ujian/Create')
        ->has('subjects')
        ->has('questionBanks')
        ->has('academicYears')
        ->has('classrooms')
    );
});

test('guru can create exam session with all fields', function () {
    $payload = [
        'name' => 'UTS Matematika Kelas X',
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'duration_minutes' => 90,
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHours(3)->toDateTimeString(),
        'is_randomize_questions' => true,
        'is_randomize_options' => true,
        'pool_count' => 3,
        'kkm' => 75,
        'max_tab_switches' => 5,
        'classroom_ids' => [$this->classroom->id],
    ];

    $response = $this->actingAs($this->guru)->post(route('guru.ujian.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('exam_sessions', [
        'name' => 'UTS Matematika Kelas X',
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'duration_minutes' => 90,
        'is_randomize_questions' => true,
        'is_randomize_options' => true,
        'pool_count' => 3,
        'kkm' => 75,
        'max_tab_switches' => 5,
        'status' => ExamStatus::Scheduled->value,
    ]);

    $session = ExamSession::where('name', 'UTS Matematika Kelas X')->first();
    // Token should be generated (6 chars, uppercase)
    expect($session->token)->toHaveLength(6);
    expect($session->token)->toMatch('/^[A-Z0-9]+$/');
    // Classroom should be attached
    expect($session->classrooms)->toHaveCount(1);
    expect($session->classrooms->first()->id)->toBe($this->classroom->id);
});

test('token is auto-generated and unique', function () {
    $payload = [
        'name' => 'Test Session 1',
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'duration_minutes' => 60,
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
        'classroom_ids' => [$this->classroom->id],
    ];

    $this->actingAs($this->guru)->post(route('guru.ujian.store'), $payload);

    $payload['name'] = 'Test Session 2';
    $this->actingAs($this->guru)->post(route('guru.ujian.store'), $payload);

    $sessions = ExamSession::where('user_id', $this->guru->id)->get();
    expect($sessions)->toHaveCount(2);
    expect($sessions[0]->token)->not->toBe($sessions[1]->token);
});

test('create exam session requires all mandatory fields', function () {
    $response = $this->actingAs($this->guru)->post(route('guru.ujian.store'), []);

    $response->assertSessionHasErrors([
        'name', 'subject_id', 'academic_year_id', 'question_bank_id',
        'duration_minutes', 'starts_at', 'ends_at', 'classroom_ids',
    ]);
});

test('ends_at must be after starts_at', function () {
    $response = $this->actingAs($this->guru)->post(route('guru.ujian.store'), [
        'name' => 'Test',
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'duration_minutes' => 60,
        'starts_at' => now()->addDay()->addHours(3)->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
        'classroom_ids' => [$this->classroom->id],
    ]);

    $response->assertSessionHasErrors('ends_at');
});

// ===== Read (Index & Show) =====

test('exam session appears in guru index', function () {
    ExamSession::factory()->count(3)->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.ujian.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Ujian/Index')
        ->has('examSessions.data', 3)
    );
});

test('guru can only see own exam sessions', function () {
    $otherGuru = User::factory()->guru()->create();

    ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);
    ExamSession::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.ujian.index'));

    $response->assertInertia(fn ($page) => $page->has('examSessions.data', 1));
});

test('guru can view exam session detail', function () {
    $session = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.ujian.show', $session));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Ujian/Show')
        ->has('examSession')
    );
});

test('guru cannot view other guru exam session detail', function () {
    $otherGuru = User::factory()->guru()->create();
    $session = ExamSession::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.ujian.show', $session));

    $response->assertForbidden();
});

// ===== Update =====

test('guru can edit own exam session', function () {
    $session = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.ujian.edit', $session));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Guru/Ujian/Edit'));
});

test('guru can update exam session', function () {
    $session = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'duration_minutes' => 60,
    ]);

    $response = $this->actingAs($this->guru)->put(route('guru.ujian.update', $session), [
        'name' => 'Nama Ujian Baru',
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'duration_minutes' => 120,
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHours(3)->toDateTimeString(),
        'classroom_ids' => [$this->classroom->id],
    ]);

    $response->assertRedirect(route('guru.ujian.show', $session));
    $response->assertSessionHas('success');

    $session->refresh();
    expect($session->name)->toBe('Nama Ujian Baru');
    expect($session->duration_minutes)->toBe(120);
});

test('guru cannot update other guru exam session', function () {
    $otherGuru = User::factory()->guru()->create();
    $session = ExamSession::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->put(route('guru.ujian.update', $session), [
        'name' => 'Hacked',
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'duration_minutes' => 60,
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
        'classroom_ids' => [$this->classroom->id],
    ]);

    $response->assertForbidden();
});

// ===== Delete =====

test('guru can delete own exam session', function () {
    $session = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->delete(route('guru.ujian.destroy', $session));

    $response->assertRedirect(route('guru.ujian.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('exam_sessions', ['id' => $session->id]);
});

test('guru cannot delete other guru exam session', function () {
    $otherGuru = User::factory()->guru()->create();
    $session = ExamSession::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->delete(route('guru.ujian.destroy', $session));

    $response->assertForbidden();
});

// ===== Status Update =====

test('guru can update exam status', function () {
    $session = ExamSession::factory()->scheduled()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)->patch(route('guru.ujian.update-status', $session), [
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $session->refresh();
    expect($session->status)->toBe(ExamStatus::Active);
});

// ===== Filtering =====

test('guru can filter exam sessions by search', function () {
    ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'name' => 'UTS Matematika Kelas X',
    ]);
    ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'name' => 'UAS Fisika Kelas XI',
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.index', ['search' => 'Matematika']));

    $response->assertInertia(fn ($page) => $page->has('examSessions.data', 1));
});

test('guru can filter exam sessions by status', function () {
    ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);
    ExamSession::factory()->completed()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.index', ['status' => 'active']));

    $response->assertInertia(fn ($page) => $page->has('examSessions.data', 1));
});
