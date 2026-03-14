<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\Artisan;
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

test('artisan command force submits expired attempts', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    // Create an expired attempt (started 2 hours ago, duration 60 min)
    $this->examSession->update(['duration_minutes' => 60]);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subHours(2),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    // Create attempt questions and answers
    foreach ($this->questions['pg'] as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => $i + 1,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer' => 'A', // correct
            'answered_at' => now()->subHour(),
        ]);
    }
    foreach ($this->questions['bs'] as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => count($this->questions['pg']) + $i + 1,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer' => 'A', // correct
            'answered_at' => now()->subHour(),
        ]);
    }
    foreach ($this->questions['esai'] as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => count($this->questions['pg']) + count($this->questions['bs']) + $i + 1,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer' => 'Jawaban esai siswa.',
            'answered_at' => now()->subHour(),
        ]);
    }

    // Run the command
    Artisan::call('exam:force-submit-expired');

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->is_force_submitted)->toBeTrue();
    expect($attempt->submitted_at)->not->toBeNull();
});

test('force submit auto-grades PG and BS answers', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    $this->examSession->update(['duration_minutes' => 60]);

    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subHours(2),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    // Only PG questions — all correct
    $pgOnlyBank = \App\Models\QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->examSession->subject_id,
    ]);
    $pgQuestions = $this->createPgQuestions($pgOnlyBank, 4);

    // Update session to use the new bank
    $this->examSession->update(['question_bank_id' => $pgOnlyBank->id]);

    foreach ($pgQuestions as $i => $q) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'order' => $i + 1,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer' => 'A', // correct
            'answered_at' => now()->subHour(),
        ]);
    }

    Artisan::call('exam:force-submit-expired');

    $attempt->refresh();
    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);

    // Each PG graded
    foreach ($attempt->answers as $answer) {
        expect($answer->is_correct)->toBeTrue();
        expect((float) $answer->score)->toBe(2.0);
    }
});

test('non-expired attempts are not force submitted', function () {
    // Attempt started 5 minutes ago, duration 60 min — NOT expired
    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subMinutes(5),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    Artisan::call('exam:force-submit-expired');

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::InProgress);
});

test('already submitted attempts are not affected by force submit', function () {
    $attempt = ExamAttempt::create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::Submitted,
    ]);

    Artisan::call('exam:force-submit-expired');

    $attempt->refresh();
    // Status should remain Submitted (not changed)
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
});

test('command outputs count of force submitted exams', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    $this->examSession->update(['duration_minutes' => 60]);

    // Create 2 expired attempts
    for ($i = 0; $i < 2; $i++) {
        $student = \App\Models\User::factory()->siswa()->create();
        $attempt = ExamAttempt::create([
            'exam_session_id' => $this->examSession->id,
            'user_id' => $student->id,
            'started_at' => now()->subHours(2),
            'ip_address' => '127.0.0.1',
            'status' => ExamAttemptStatus::InProgress,
        ]);

        // Need at least one answer for grading not to error
        foreach ($this->questions['pg'] as $idx => $q) {
            ExamAttemptQuestion::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'order' => $idx + 1,
            ]);
            StudentAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'answer' => null,
            ]);
        }
        foreach ($this->questions['bs'] as $idx => $q) {
            ExamAttemptQuestion::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'order' => count($this->questions['pg']) + $idx + 1,
            ]);
            StudentAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'answer' => null,
            ]);
        }
        foreach ($this->questions['esai'] as $idx => $q) {
            ExamAttemptQuestion::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'order' => count($this->questions['pg']) + count($this->questions['bs']) + $idx + 1,
            ]);
            StudentAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'answer' => null,
            ]);
        }
    }

    $exitCode = Artisan::call('exam:force-submit-expired');

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('2');
});
