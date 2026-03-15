<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
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
use App\Services\Exam\GradingService;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);
    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $this->classroom->students()->attach($this->siswa->id);

    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    // Create a completed exam session with mix of PG + Esai
    $this->session = ExamSession::factory()->completed()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'kkm' => 75.00,
        'is_results_published' => false,
    ]);
    $this->session->classrooms()->attach($this->classroom->id);

    $this->pgQuestion = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $this->pgQuestion->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $this->pgQuestion->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $this->esaiQuestion = Question::factory()->esai()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 10,
        'order' => 2,
        'explanation' => 'Pembahasan soal esai.',
    ]);

    // Create a submitted attempt
    $this->attempt = ExamAttempt::create([
        'exam_session_id' => $this->session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subHours(3),
        'submitted_at' => now()->subHours(2),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::Submitted,
        'is_fully_graded' => false,
    ]);

    foreach ([$this->pgQuestion, $this->esaiQuestion] as $i => $question) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $this->attempt->id,
            'question_id' => $question->id,
            'order' => $i + 1,
        ]);
    }

    // PG answer already auto-graded
    $this->pgAnswer = StudentAnswer::create([
        'exam_attempt_id' => $this->attempt->id,
        'question_id' => $this->pgQuestion->id,
        'answer' => 'A',
        'is_correct' => true,
        'score' => 5,
        'answered_at' => now()->subHours(3),
    ]);

    // Esai answer not yet graded
    $this->esaiAnswer = StudentAnswer::create([
        'exam_attempt_id' => $this->attempt->id,
        'question_id' => $this->esaiQuestion->id,
        'answer' => 'Jawaban esai dari siswa.',
        'answered_at' => now()->subHours(3),
    ]);
});

// =====================
// Manual Grading Flow
// =====================

test('guru can view grading index', function () {
    $response = $this->actingAs($this->guru)->get('/guru/grading');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Penilaian/Index')
        ->has('examSessions.data', 1)
    );
});

test('guru can view exam results page', function () {
    $response = $this->actingAs($this->guru)->get("/guru/grading/{$this->session->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Penilaian/Show')
        ->has('attempts', 1)
        ->has('statistics')
        ->has('progress')
    );
});

test('guru can view manual grading interface', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/grading/{$this->session->id}/attempt/{$this->attempt->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Penilaian/ManualGrading')
        ->has('answers', 2)
        ->has('otherAttempts')
        ->has('gradingProgress')
    );
});

test('guru can save manual grade for esai answer', function () {
    $response = $this->actingAs($this->guru)
        ->post("/guru/grading/{$this->session->id}/answer/{$this->esaiAnswer->id}", [
            'score' => 8,
            'feedback' => 'Jawaban bagus, tapi kurang lengkap.',
        ]);

    $response->assertRedirect();

    $this->esaiAnswer->refresh();
    expect((float) $this->esaiAnswer->score)->toBe(8.0);
    expect($this->esaiAnswer->feedback)->toBe('Jawaban bagus, tapi kurang lengkap.');
    expect($this->esaiAnswer->is_correct)->toBeTrue();

    // Attempt should be fully graded now
    $this->attempt->refresh();
    expect($this->attempt->is_fully_graded)->toBeTrue();
    expect($this->attempt->status)->toBe(ExamAttemptStatus::Graded);
});

test('manual grade score cannot exceed max points', function () {
    $service = app(GradingService::class);
    $service->saveGrade($this->esaiAnswer, 999, null);

    $this->esaiAnswer->refresh();
    expect((float) $this->esaiAnswer->score)->toBe(10.0); // capped to max points
});

test('manual grade score cannot be negative', function () {
    $service = app(GradingService::class);
    $service->saveGrade($this->esaiAnswer, -5, null);

    $this->esaiAnswer->refresh();
    expect((float) $this->esaiAnswer->score)->toBe(0.0);
});

test('guru cannot grade another guru\'s exam', function () {
    $otherGuru = User::factory()->guru()->create();

    $response = $this->actingAs($otherGuru)
        ->get("/guru/grading/{$this->session->id}");

    $response->assertStatus(403);
});

// =====================
// Publish/Unpublish
// =====================

test('guru can publish exam results', function () {
    $response = $this->actingAs($this->guru)
        ->patch("/guru/grading/{$this->session->id}/publish");

    $response->assertRedirect();

    $this->session->refresh();
    expect($this->session->is_results_published)->toBeTrue();
});

test('guru can unpublish exam results', function () {
    $this->session->update(['is_results_published' => true]);

    $response = $this->actingAs($this->guru)
        ->patch("/guru/grading/{$this->session->id}/unpublish");

    $response->assertRedirect();

    $this->session->refresh();
    expect($this->session->is_results_published)->toBeFalse();
});

// =====================
// Siswa View Results
// =====================

test('siswa can view published results list', function () {
    $this->session->update(['is_results_published' => true]);

    $response = $this->actingAs($this->siswa)->get('/siswa/nilai');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Nilai/Index')
        ->has('results', 1)
    );
});

test('siswa cannot see unpublished results in list', function () {
    // is_results_published is false by default
    $response = $this->actingAs($this->siswa)->get('/siswa/nilai');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Nilai/Index')
        ->has('results', 0)
    );
});

test('siswa can view published result detail', function () {
    $this->session->update(['is_results_published' => true]);

    $response = $this->actingAs($this->siswa)
        ->get("/siswa/nilai/{$this->attempt->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Nilai/Show')
        ->has('answers', 2)
        ->has('examSession')
    );
});

test('siswa cannot view unpublished result detail', function () {
    $response = $this->actingAs($this->siswa)
        ->get("/siswa/nilai/{$this->attempt->id}");

    $response->assertStatus(403);
});

test('siswa cannot view another student\'s result', function () {
    $this->session->update(['is_results_published' => true]);

    $otherSiswa = User::factory()->siswa()->create();

    $response = $this->actingAs($otherSiswa)
        ->get("/siswa/nilai/{$this->attempt->id}");

    $response->assertStatus(403);
});

// =====================
// Export
// =====================

test('guru can export exam results as CSV', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/grading/{$this->session->id}/export");

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

// =====================
// GradingService
// =====================

test('grading service calculates correct statistics', function () {
    // Grade the esai so we have complete data
    $this->esaiAnswer->update(['score' => 8, 'is_correct' => true]);
    $this->attempt->update([
        'score' => 86.67, // (5+8)/15 * 100
        'is_fully_graded' => true,
        'status' => ExamAttemptStatus::Graded,
    ]);

    $service = app(GradingService::class);
    $stats = $service->getExamStatistics($this->session);

    expect($stats['total_students'])->toBe(1);
    expect($stats['average'])->toBe(86.67);
    expect($stats['highest'])->toBe(86.67);
    expect($stats['lowest'])->toBe(86.67);
    expect($stats['passed'])->toBe(1); // >= 75 KKM
    expect($stats['failed'])->toBe(0);
});

test('grading service tracks grading progress', function () {
    $service = app(GradingService::class);
    $progress = $service->getGradingProgress($this->session);

    expect($progress['total_attempts'])->toBe(1);
    expect($progress['fully_graded'])->toBe(0);
    expect($progress['ungraded_essays'])->toBe(1);
});
