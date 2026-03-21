<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\SingleSessionExam::class);

    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();

    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);

    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);

    // Assign siswa ke kelas
    $this->classroom->students()->attach($this->siswa->id);

    // Create question bank with questions
    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    // PG question
    $this->pgQuestion = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $this->pgQuestion->id,
        'label' => 'A',
        'content' => 'Jawaban benar',
        'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $this->pgQuestion->id,
        'label' => 'B',
        'content' => 'Jawaban salah',
        'order' => 1,
    ]);

    // B/S question
    $this->bsQuestion = Question::factory()->benarSalah()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 2,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $this->bsQuestion->id,
        'label' => 'A',
        'content' => 'Benar',
        'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $this->bsQuestion->id,
        'label' => 'B',
        'content' => 'Salah',
        'order' => 1,
    ]);

    // Esai question
    $this->esaiQuestion = Question::factory()->esai()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 10,
        'order' => 3,
    ]);

    // Active exam session
    $this->examSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'token' => 'ABCDEF',
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);

    $this->examSession->classrooms()->attach($this->classroom->id);
});

// ===== Token Verification =====

test('siswa can view token verification page', function () {
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.verify-token', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Siswa/Ujian/VerifyToken'));
});

test('siswa can verify correct token', function () {
    $response = $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.verify-token', $this->examSession), [
            'token' => 'ABCDEF',
        ]);

    $response->assertRedirect(route('siswa.ujian.start', $this->examSession));
});

test('siswa cannot verify with wrong token', function () {
    $response = $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.verify-token', $this->examSession), [
            'token' => 'WRONG1',
        ]);

    $response->assertSessionHasErrors('token');
});

// ===== Start Exam =====

test('siswa can start exam', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/ExamInterface')
        ->has('questions', 3)
        ->has('exam')
        ->has('remaining_seconds')
    );

    // Verify attempt was created
    $this->assertDatabaseHas('exam_attempts', [
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'status' => ExamAttemptStatus::InProgress->value,
    ]);

    // Verify student_answers were pre-created
    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
    expect($attempt->answers)->toHaveCount(3);
});

test('siswa cannot start exam that is not active', function () {
    $this->examSession->update(['status' => ExamStatus::Scheduled]);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
});

test('siswa cannot start exam twice', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // Start first time
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    // Mark as submitted
    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
    $attempt->update(['status' => ExamAttemptStatus::Submitted, 'submitted_at' => now()]);

    // Try to start again
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
});

test('siswa can resume in-progress exam', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // Start exam first
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    // Resume
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/ExamInterface')
    );
});

// ===== Save Answers =====

test('siswa can save answers', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->times(3)->andReturn(true);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $response = $this->actingAs($this->siswa)
        ->postJson(route('siswa.ujian.save-answers', $this->examSession), [
            'answers' => [
                (string) $this->pgQuestion->id => 'A',
                (string) $this->bsQuestion->id => 'A',
            ],
            'flags' => [$this->esaiQuestion->id],
        ]);

    $response->assertOk();
    $response->assertJson(['saved' => true]);
});

test('siswa cannot save answers without active attempt', function () {
    $response = $this->actingAs($this->siswa)
        ->postJson(route('siswa.ujian.save-answers', $this->examSession), [
            'answers' => [(string) $this->pgQuestion->id => 'A'],
        ]);

    $response->assertNotFound();
});

// ===== Submit Exam =====

test('siswa can submit exam', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response = $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.submit', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
    $response->assertSessionHas('success');

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->submitted_at)->not->toBeNull();
});

test('siswa cannot submit exam without active attempt', function () {
    $response = $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.submit', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
});

// ===== Exam List =====

test('siswa can see available exams for their class', function () {
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/Index')
        ->has('examSessions', 1)
    );
});

test('siswa cannot see exams for other classes', function () {
    $otherClassroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
    ]);

    $otherSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);
    $otherSession->classrooms()->attach($otherClassroom->id);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.index'));

    // Should only see the exam assigned to their classroom
    $response->assertInertia(fn ($page) => $page
        ->has('examSessions', 1)
    );
});
