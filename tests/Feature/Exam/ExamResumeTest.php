<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment();
    $this->siswa = $env['siswa'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];
});

test('resume after crash returns same attempt with answers preserved', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // 1. Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
    $originalAttemptId = $attempt->id;
    $originalStartedAt = $attempt->started_at->timestamp;

    // 2. Simulate answering (write directly to DB like if auto-save persisted)
    $pgQ = $this->questions['pg'][0];
    StudentAnswer::where('exam_attempt_id', $attempt->id)
        ->where('question_id', $pgQ->id)
        ->update(['answer' => 'B', 'answered_at' => now()]);

    // 3. "Crash" — request start again
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Siswa/Ujian/ExamInterface'));

    // 4. Verify same attempt (not a new one)
    $attempts = ExamAttempt::where('user_id', $this->siswa->id)->get();
    expect($attempts)->toHaveCount(1);
    expect($attempts->first()->id)->toBe($originalAttemptId);
    expect($attempts->first()->started_at->timestamp)->toBe($originalStartedAt);
});

test('resume preserves saved answers from DB', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // 1. Start
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // 2. Save answer to DB
    $pgQ = $this->questions['pg'][0];
    StudentAnswer::where('exam_attempt_id', $attempt->id)
        ->where('question_id', $pgQ->id)
        ->update(['answer' => 'C', 'answered_at' => now()]);

    // 3. Resume via /exam endpoint
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertOk();
    $response->assertInertia(function ($page) use ($pgQ) {
        $savedAnswers = (array) $page->toArray()['props']['saved_answers'];
        expect($savedAnswers[(string) $pgQ->id])->toBe('C');
    });
});

test('resume preserves answers from Redis over DB', function () {
    $pgQ = $this->questions['pg'][0];
    $bsQ = $this->questions['bs'][0];

    // First call to Redis::get (answers key) returns Redis data
    // Second call (flags key) returns null
    Redis::shouldReceive('get')
        ->andReturnUsing(function (string $key) use ($pgQ, $bsQ) {
            if (str_contains($key, ':answers')) {
                return json_encode([
                    (string) $pgQ->id => 'D',
                    (string) $bsQ->id => 'B',
                ]);
            }

            return null;
        });

    // Start
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // Save older answer to DB
    StudentAnswer::where('exam_attempt_id', $attempt->id)
        ->where('question_id', $pgQ->id)
        ->update(['answer' => 'A', 'answered_at' => now()]);

    // Resume — Redis data should override DB
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertInertia(function ($page) use ($pgQ, $bsQ) {
        $savedAnswers = (array) $page->toArray()['props']['saved_answers'];
        // Redis answer 'D' should override DB answer 'A'
        expect($savedAnswers[(string) $pgQ->id])->toBe('D');
        expect($savedAnswers[(string) $bsQ->id])->toBe('B');
    });
});

test('timer continues from where it left off, not reset', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // 1. Start
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // 2. Simulate time passing (set started_at to 10 minutes ago)
    $attempt->update(['started_at' => now()->subMinutes(10)]);

    // 3. Resume
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertInertia(function ($page) {
        $remaining = $page->toArray()['props']['remaining_seconds'];
        $durationTotal = $this->examSession->duration_minutes * 60;

        // Should be roughly durationTotal - 600 (10 minutes), not full duration
        // Allow 5 seconds tolerance for test execution time
        expect($remaining)->toBeLessThan($durationTotal - 590);
        expect($remaining)->toBeGreaterThan(0);
    });
});

test('resume with flags preserves flagged questions from Redis', function () {
    $pgQ = $this->questions['pg'][0];
    $bsQ = $this->questions['bs'][0];

    Redis::shouldReceive('get')
        ->andReturnUsing(function (string $key) use ($pgQ, $bsQ) {
            if (str_contains($key, ':flags')) {
                return json_encode([$pgQ->id, $bsQ->id]);
            }

            return null;
        });

    // Start
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    // Resume
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertInertia(function ($page) use ($pgQ, $bsQ) {
        $flagged = $page->toArray()['props']['flagged_questions'];
        expect($flagged)->toContain($pgQ->id);
        expect($flagged)->toContain($bsQ->id);
    });
});
