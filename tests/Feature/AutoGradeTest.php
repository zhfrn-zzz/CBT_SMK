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
use App\Services\Exam\ExamAttemptService;
use Illuminate\Support\Facades\Redis;

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
});

test('auto-grade gives full score for all correct PG answers', function () {
    // Create 5 PG questions
    $questions = [];
    for ($i = 1; $i <= 5; $i++) {
        $q = Question::factory()->pilihanGanda()->create([
            'question_bank_id' => $this->questionBank->id,
            'points' => 2,
            'order' => $i,
        ]);
        QuestionOption::factory()->correct()->create([
            'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah 1', 'order' => 1,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $q->id, 'label' => 'C', 'content' => 'Salah 2', 'order' => 2,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $q->id, 'label' => 'D', 'content' => 'Salah 3', 'order' => 3,
        ]);
        $questions[] = $q;
    }

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    // Create attempt and answers manually to bypass Redis
    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ($questions as $i => $question) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'order' => $i + 1,
        ]);

        // All correct answers (label A)
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer' => 'A',
            'answered_at' => now(),
        ]);
    }

    // Submit (bypass Redis)
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $service = app(ExamAttemptService::class);
    $service->submitExam($attempt);

    $attempt->refresh();

    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);

    // Verify each answer was graded correctly
    foreach ($attempt->answers as $answer) {
        expect($answer->is_correct)->toBeTrue();
        expect((float) $answer->score)->toBe(2.0);
    }
});

test('auto-grade gives zero for all wrong answers', function () {
    // Create 3 PG questions
    $questions = [];
    for ($i = 1; $i <= 3; $i++) {
        $q = Question::factory()->pilihanGanda()->create([
            'question_bank_id' => $this->questionBank->id,
            'points' => 2,
            'order' => $i,
        ]);
        QuestionOption::factory()->correct()->create([
            'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
        ]);
        $questions[] = $q;
    }

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ($questions as $i => $question) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'order' => $i + 1,
        ]);

        // All wrong answers (label B)
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer' => 'B',
            'answered_at' => now(),
        ]);
    }

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $service = app(ExamAttemptService::class);
    $service->submitExam($attempt);

    $attempt->refresh();

    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(0.0);

    foreach ($attempt->answers as $answer) {
        expect($answer->is_correct)->toBeFalse();
        expect((float) $answer->score)->toBe(0.0);
    }
});

test('auto-grade gives partial score for mix correct and wrong', function () {
    // Create 4 PG questions
    $questions = [];
    for ($i = 1; $i <= 4; $i++) {
        $q = Question::factory()->pilihanGanda()->create([
            'question_bank_id' => $this->questionBank->id,
            'points' => 2,
            'order' => $i,
        ]);
        QuestionOption::factory()->correct()->create([
            'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
        ]);
        $questions[] = $q;
    }

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ($questions as $i => $question) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'order' => $i + 1,
        ]);

        // First 2 correct, last 2 wrong → 50%
        $answer = $i < 2 ? 'A' : 'B';
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer' => $answer,
            'answered_at' => now(),
        ]);
    }

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $service = app(ExamAttemptService::class);
    $service->submitExam($attempt);

    $attempt->refresh();

    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(50.0);
});

test('auto-grade correctly handles benar-salah questions', function () {
    // Create 2 B/S questions
    $q1 = Question::factory()->benarSalah()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $q1->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $q1->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $q2 = Question::factory()->benarSalah()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 2,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $q2->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $q2->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    // Q1 correct=A → answer A (correct), Q2 correct=B → answer B (correct)
    foreach ([$q1, $q2] as $i => $question) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'order' => $i + 1,
        ]);
    }

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'answer' => 'A',
        'answered_at' => now(),
    ]);
    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $q2->id,
        'answer' => 'B',
        'answered_at' => now(),
    ]);

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $service = app(ExamAttemptService::class);
    $service->submitExam($attempt);

    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);
});

test('exam with esai is not fully graded after auto-grade', function () {
    // Mix of PG + Esai
    $pgQ = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $pgQ->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $pgQ->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $esaiQ = Question::factory()->esai()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 10,
        'order' => 2,
    ]);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ([$pgQ, $esaiQ] as $i => $question) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'order' => $i + 1,
        ]);
    }

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $pgQ->id,
        'answer' => 'A',
        'answered_at' => now(),
    ]);
    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $esaiQ->id,
        'answer' => 'Jawaban esai siswa tentang materi ini.',
        'answered_at' => now(),
    ]);

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $service = app(ExamAttemptService::class);
    $service->submitExam($attempt);

    $attempt->refresh();

    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->is_fully_graded)->toBeFalse();

    // PG answer should be graded
    $pgAnswer = $attempt->answers->firstWhere('question_id', $pgQ->id);
    expect($pgAnswer->is_correct)->toBeTrue();
    expect((float) $pgAnswer->score)->toBe(2.0);

    // Esai answer should NOT be graded
    $esaiAnswer = $attempt->answers->firstWhere('question_id', $esaiQ->id);
    expect($esaiAnswer->is_correct)->toBeNull();
    expect($esaiAnswer->score)->toBeNull();
});

test('unanswered questions get zero score', function () {
    $q = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $q->id,
        'order' => 1,
    ]);

    // Pre-created empty answer (no answer given)
    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $q->id,
        'answer' => null,
    ]);

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $service = app(ExamAttemptService::class);
    $service->submitExam($attempt);

    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(0.0);

    $answer = $attempt->answers->first();
    expect($answer->is_correct)->toBeFalse();
    expect((float) $answer->score)->toBe(0.0);
});

test('force submit sets is_force_submitted flag', function () {
    $q = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $q->id,
        'order' => 1,
    ]);

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $q->id,
        'answer' => 'A',
        'answered_at' => now(),
    ]);

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $service = app(ExamAttemptService::class);
    $service->submitExam($attempt, isForceSubmit: true);

    $attempt->refresh();

    expect($attempt->is_force_submitted)->toBeTrue();
});
