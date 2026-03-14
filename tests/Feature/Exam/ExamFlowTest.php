<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment();
    $this->guru = $env['guru'];
    $this->siswa = $env['siswa'];
    $this->classroom = $env['classroom'];
    $this->questionBank = $env['questionBank'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];
});

// ===== Token Verification =====

test('siswa can verify correct token (case insensitive)', function () {
    $response = $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.verify-token', $this->examSession), [
            'token' => 'abcdef', // lowercase — should match ABCDEF
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

test('token verification fails when exam is not active', function () {
    $this->examSession->update(['status' => ExamStatus::Scheduled]);

    $response = $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.verify-token', $this->examSession), [
            'token' => 'ABCDEF',
        ]);

    $response->assertSessionHasErrors('token');
});

// ===== Start Exam =====

test('start exam creates attempt, attempt_questions, and empty student_answers', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertOk();

    // Attempt created
    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe(ExamAttemptStatus::InProgress);
    expect($attempt->started_at)->not->toBeNull();

    // All 6 questions get attempt_questions
    expect($attempt->attemptQuestions)->toHaveCount(6);

    // Pre-created empty student_answers
    expect($attempt->answers)->toHaveCount(6);
    expect($attempt->answers->every(fn ($a) => $a->answer === null))->toBeTrue();
});

test('start exam returns all questions with correct structure', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/ExamInterface')
        ->has('questions', 6)
        ->has('exam', fn ($exam) => $exam
            ->has('id')
            ->has('name')
            ->has('subject')
            ->has('duration_minutes')
            ->has('total_questions')
            ->has('max_tab_switches')
        )
        ->has('remaining_seconds')
        ->has('saved_answers')
        ->has('flagged_questions')
        ->has('started_at')
        ->has('server_time')
        ->has('attempt_id')
    );
});

test('PG questions have options, esai questions do not', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertInertia(function ($page) {
        $questions = $page->toArray()['props']['questions'];

        // PG and B/S should have options
        $pgBsQuestions = array_filter($questions, fn ($q) => in_array($q['type'], ['pilihan_ganda', 'benar_salah']));
        foreach ($pgBsQuestions as $q) {
            expect($q['options'])->not->toBeNull();
            expect(count($q['options']))->toBeGreaterThan(0);
        }

        // Esai should not have options
        $esaiQuestions = array_filter($questions, fn ($q) => $q['type'] === 'esai');
        foreach ($esaiQuestions as $q) {
            expect($q['options'])->toBeNull();
        }
    });
});

// ===== Save Answers =====

test('save answers stores data in Redis and returns sync data', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->times(3)->andReturn(true);

    // Start exam first
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $pgQuestion = $this->questions['pg'][0];

    $response = $this->actingAs($this->siswa)
        ->postJson(route('siswa.ujian.save-answers', $this->examSession), [
            'answers' => [(string) $pgQuestion->id => 'A'],
            'flags' => [$pgQuestion->id],
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['saved', 'server_time', 'remaining_seconds']);
    $response->assertJson(['saved' => true]);
});

// ===== Submit Exam =====

test('submit exam persists answers, auto-grades PG and BS, calculates score', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // Manually set answers (simulating what Redis would do)
    foreach ($this->questions['pg'] as $q) {
        StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $q->id)
            ->update(['answer' => 'A', 'answered_at' => now()]); // A = correct
    }
    foreach ($this->questions['bs'] as $q) {
        StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $q->id)
            ->update(['answer' => 'A', 'answered_at' => now()]); // A = correct (Benar)
    }
    StudentAnswer::where('exam_attempt_id', $attempt->id)
        ->where('question_id', $this->questions['esai'][0]->id)
        ->update(['answer' => 'Jawaban esai panjang.', 'answered_at' => now()]);

    // Submit
    $response = $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.submit', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
    $response->assertSessionHas('success');

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->submitted_at)->not->toBeNull();

    // PG answers should be auto-graded
    foreach ($this->questions['pg'] as $q) {
        $answer = $attempt->answers->firstWhere('question_id', $q->id);
        expect($answer->is_correct)->toBeTrue();
        expect((float) $answer->score)->toBe(2.0);
    }

    // B/S answers should be auto-graded
    foreach ($this->questions['bs'] as $q) {
        $answer = $attempt->answers->firstWhere('question_id', $q->id);
        expect($answer->is_correct)->toBeTrue();
        expect((float) $answer->score)->toBe(2.0);
    }

    // Esai should NOT be auto-graded
    $esaiAnswer = $attempt->answers->firstWhere('question_id', $this->questions['esai'][0]->id);
    expect($esaiAnswer->is_correct)->toBeNull();
    expect($esaiAnswer->score)->toBeNull();

    // is_fully_graded should be false because esai is not graded
    expect($attempt->is_fully_graded)->toBeFalse();
});

test('submitted exam cannot be opened again', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    // Start
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    // Submit
    $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.submit', $this->examSession));

    // Try to start again
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
});

test('submitted exam cannot be resumed', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    // Start
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    // Submit
    $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.submit', $this->examSession));

    // Try to resume
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
});

// ===== Exam List =====

test('siswa exam index shows upcoming, active, and completed exams', function () {
    // Already have 1 active session in beforeEach
    // Add a completed session
    $completedSession = \App\Models\ExamSession::factory()->completed()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->examSession->subject_id,
        'academic_year_id' => $this->examSession->academic_year_id,
        'question_bank_id' => $this->examSession->question_bank_id,
    ]);
    $completedSession->classrooms()->attach($this->classroom->id);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/Index')
        ->has('examSessions', 2)
    );
});
