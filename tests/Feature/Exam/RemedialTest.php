<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment([
        'kkm' => 70,
        'is_results_published' => true,
        'status' => ExamStatus::Completed,
    ]);
    $this->guru = $env['guru'];
    $this->siswa = $env['siswa'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];
    $this->questionBank = $env['questionBank'];
    $this->subject = $env['subject'];
    $this->academicYear = $env['academicYear'];
    $this->classroom = $env['classroom'];

    // Create a submitted attempt with a failing score
    $this->attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subHour(),
        'submitted_at' => now(),
        'status' => ExamAttemptStatus::Submitted,
        'score' => 50.00,
        'is_fully_graded' => true,
        'is_force_submitted' => false,
    ]);
});

// ===== Guru creates remedial exam =====

test('guru can view create remedial form', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.create-remedial', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Ujian/CreateRemedial')
        ->has('originalExam')
        ->where('originalExam.id', $this->examSession->id)
        ->where('originalExam.name', $this->examSession->name)
        ->has('remedialStudents', 1)
        ->where('remedialStudents.0.id', $this->siswa->id)
        ->has('subjects')
        ->has('questionBanks')
    );
});

test('guru can create remedial exam with highest policy', function () {
    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.store-remedial', $this->examSession), [
            'name' => 'Remedial - Test Exam',
            'subject_id' => $this->subject->id,
            'academic_year_id' => $this->academicYear->id,
            'question_bank_id' => $this->questionBank->id,
            'duration_minutes' => 60,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'is_randomize_questions' => true,
            'is_randomize_options' => true,
            'classroom_ids' => [$this->classroom->id],
            'remedial_policy' => 'highest',
        ]);

    $response->assertRedirect();

    $remedialExam = ExamSession::where('original_exam_session_id', $this->examSession->id)->first();
    expect($remedialExam)->not->toBeNull();
    expect($remedialExam->original_exam_session_id)->toBe($this->examSession->id);
    expect($remedialExam->remedial_policy)->toBe('highest');
    expect($remedialExam->status)->toBe(ExamStatus::Scheduled);
    expect($remedialExam->isRemedial())->toBeTrue();
});

test('guru can create remedial exam with capped_at_kkm policy', function () {
    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.store-remedial', $this->examSession), [
            'name' => 'Remedial - Capped',
            'subject_id' => $this->subject->id,
            'academic_year_id' => $this->academicYear->id,
            'question_bank_id' => $this->questionBank->id,
            'duration_minutes' => 60,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'is_randomize_questions' => true,
            'is_randomize_options' => true,
            'classroom_ids' => [$this->classroom->id],
            'remedial_policy' => 'capped_at_kkm',
        ]);

    $response->assertRedirect();

    $remedialExam = ExamSession::where('name', 'Remedial - Capped')->first();
    expect($remedialExam->remedial_policy)->toBe('capped_at_kkm');
});

test('remedial exam can use different question bank', function () {
    // Create another question bank with questions
    $anotherBank = \App\Models\QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);
    $this->createPgQuestions($anotherBank, 5);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.store-remedial', $this->examSession), [
            'name' => 'Remedial - Different Bank',
            'subject_id' => $this->subject->id,
            'academic_year_id' => $this->academicYear->id,
            'question_bank_id' => $anotherBank->id,
            'duration_minutes' => 45,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'is_randomize_questions' => true,
            'is_randomize_options' => true,
            'classroom_ids' => [$this->classroom->id],
            'remedial_policy' => 'highest',
        ]);

    $response->assertRedirect();

    $remedialExam = ExamSession::where('name', 'Remedial - Different Bank')->first();
    expect($remedialExam->question_bank_id)->toBe($anotherBank->id);
});

test('remedial exam stores link to original exam', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.ujian.store-remedial', $this->examSession), [
            'name' => 'Remedial Link Test',
            'subject_id' => $this->subject->id,
            'academic_year_id' => $this->academicYear->id,
            'question_bank_id' => $this->questionBank->id,
            'duration_minutes' => 60,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'is_randomize_questions' => true,
            'is_randomize_options' => true,
            'classroom_ids' => [$this->classroom->id],
            'remedial_policy' => 'highest',
        ]);

    $remedialExam = ExamSession::where('name', 'Remedial Link Test')->first();
    expect($remedialExam->originalExamSession->id)->toBe($this->examSession->id);

    // Original exam should know about remedials
    $this->examSession->refresh();
    expect($this->examSession->remedialExamSessions)->toHaveCount(1);
});

test('remedial policy validation rejects invalid value', function () {
    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.store-remedial', $this->examSession), [
            'name' => 'Remedial Invalid',
            'subject_id' => $this->subject->id,
            'academic_year_id' => $this->academicYear->id,
            'question_bank_id' => $this->questionBank->id,
            'duration_minutes' => 60,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'is_randomize_questions' => true,
            'is_randomize_options' => true,
            'classroom_ids' => [$this->classroom->id],
            'remedial_policy' => 'invalid_policy',
        ]);

    $response->assertSessionHasErrors('remedial_policy');
});

// ===== Siswa sees remedial indicator =====

test('siswa exam list shows remedial badge', function () {
    // Create the remedial exam
    $remedialExam = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'original_exam_session_id' => $this->examSession->id,
        'remedial_policy' => 'highest',
    ]);
    $remedialExam->classrooms()->attach($this->classroom->id);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.index'));

    $response->assertOk();

    // Check the rendered data directly
    $page = $response->original->getData()['page']['props'];
    $sessions = collect($page['examSessions']);
    $remedialSession = $sessions->firstWhere('id', $remedialExam->id);
    expect($remedialSession)->not->toBeNull();
    expect($remedialSession['is_remedial'])->toBeTrue();
});

test('siswa results show remedial indicator', function () {
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.nilai.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('results', 1)
        ->where('results.0.is_remedial', false) // original exam
    );
});

// ===== Grading show includes remedial info =====

test('grading show displays remedial exams', function () {
    $remedialExam = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'original_exam_session_id' => $this->examSession->id,
        'remedial_policy' => 'highest',
        'status' => ExamStatus::Scheduled,
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.show', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->has('remedialExams', 1)
        ->where('remedialExams.0.id', $remedialExam->id)
        ->where('remedialExams.0.remedial_policy', 'highest')
        ->where('isRemedial', false) // the original exam itself is not remedial
    );
});

test('grading show marks students below kkm as remedial', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.show', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->has('attempts', 1)
        ->where('attempts.0.pass_status', 'remedial')
    );
});

test('grading show includes violation count per attempt', function () {
    // Create some violations
    \App\Models\ExamActivityLog::create([
        'exam_attempt_id' => $this->attempt->id,
        'event_type' => 'tab_switch',
        'description' => 'Test violation',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.show', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->has('attempts', 1)
        ->where('attempts.0.violation_count', 1)
    );
});

// ===== Remedial students detection =====

test('only students below kkm appear in remedial students list', function () {
    // Create a passing student
    $passingSiswa = User::factory()->siswa()->create();
    $this->classroom->students()->attach($passingSiswa->id);

    ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $passingSiswa->id,
        'started_at' => now()->subHour(),
        'submitted_at' => now(),
        'status' => ExamAttemptStatus::Submitted,
        'score' => 85.00,
        'is_fully_graded' => true,
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.create-remedial', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->has('remedialStudents', 1) // only the failing student
        ->where('remedialStudents.0.id', $this->siswa->id)
    );
});
