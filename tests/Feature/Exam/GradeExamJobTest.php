<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Jobs\GradeExamJob;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\StudentAnswer;
use App\Services\Exam\ExamAttemptService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment();
    $this->guru = $env['guru'];
    $this->siswa = $env['siswa'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];
    $this->questionBank = $env['questionBank'];
});

// ===== GradeExamJob dispatch =====

test('submitExam dispatches GradeExamJob', function () {
    Bus::fake([GradeExamJob::class]);

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ($this->questions['pg'] as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => $i + 1,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer' => 'A',
            'answered_at' => now(),
        ]);
    }

    app(ExamAttemptService::class)->submitExam($attempt);

    Bus::assertDispatched(GradeExamJob::class, function (GradeExamJob $job) use ($attempt) {
        return $job->attempt->id === $attempt->id;
    });

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
});

// ===== GradeExamJob execution =====

test('GradeExamJob auto-grades answers when executed', function () {
    $questions = $this->createPgQuestions($this->questionBank, 3);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::Submitted,
        'submitted_at' => now(),
    ]);

    foreach ($questions as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => $i + 1,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer' => 'A',
            'answered_at' => now(),
        ]);
    }

    // Directly execute the job
    $job = new GradeExamJob($attempt);
    $job->handle(app(ExamAttemptService::class));

    $attempt->refresh();
    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);

    foreach ($attempt->answers as $answer) {
        expect($answer->is_correct)->toBeTrue();
        expect((float) $answer->score)->toBe(2.0);
    }
});

// ===== Batch upsert in submitExam =====

test('submitExam batch upserts Redis answers to MySQL', function () {
    $pgQuestions = $this->questions['pg'];

    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ($pgQuestions as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => $i + 1,
        ]);
        // Pre-create empty student answers (like startExam does)
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
        ]);
    }

    // Simulate Redis answers
    $redisAnswers = [];
    foreach ($pgQuestions as $q) {
        $redisAnswers[(string) $q->id] = 'A';
    }

    Redis::shouldReceive('get')
        ->once()
        ->andReturn(json_encode($redisAnswers));
    Redis::shouldReceive('get')
        ->once()
        ->andReturn(null); // flags
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    app(ExamAttemptService::class)->submitExam($attempt);

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);

    // Verify all answers were upserted
    foreach ($pgQuestions as $q) {
        $answer = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $q->id)
            ->first();
        expect($answer->answer)->toBe('A');
        expect($answer->answered_at)->not->toBeNull();
    }
});

test('batch upsert updates existing answers without creating duplicates', function () {
    $pgQuestions = $this->questions['pg'];

    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ($pgQuestions as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => $i + 1,
        ]);
        // Pre-create with old answers
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer' => 'B',
            'answered_at' => now()->subMinutes(5),
        ]);
    }

    $initialCount = StudentAnswer::where('exam_attempt_id', $attempt->id)->count();

    // Redis has updated answers
    $redisAnswers = [];
    foreach ($pgQuestions as $q) {
        $redisAnswers[(string) $q->id] = 'A';
    }

    Redis::shouldReceive('get')
        ->once()
        ->andReturn(json_encode($redisAnswers));
    Redis::shouldReceive('get')
        ->once()
        ->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    app(ExamAttemptService::class)->submitExam($attempt);

    // Count should remain the same (upsert, not insert)
    $finalCount = StudentAnswer::where('exam_attempt_id', $attempt->id)->count();
    expect($finalCount)->toBe($initialCount);

    // Answers should be updated to 'A'
    foreach ($pgQuestions as $q) {
        $answer = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $q->id)
            ->first();
        expect($answer->answer)->toBe('A');
    }
});
