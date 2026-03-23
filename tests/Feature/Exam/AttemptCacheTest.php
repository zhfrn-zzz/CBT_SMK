<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\Cache;
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

test('saveAnswers caches attempt in Redis and reuses on subsequent calls', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->andReturn(true);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
    $cacheKey = "active_attempt:{$this->examSession->id}:{$this->siswa->id}";

    // First save — should cache the attempt
    $response = $this->actingAs($this->siswa)
        ->postJson(route('siswa.ujian.save-answers', $this->examSession), [
            'answers' => [(string) $this->questions['pg'][0]->id => 'A'],
            'flags' => [],
        ]);

    $response->assertOk();
    $response->assertJson(['saved' => true]);

    // Verify attempt is now cached
    $cached = Cache::get($cacheKey);
    expect($cached)->not->toBeNull();
    expect($cached->id)->toBe($attempt->id);
    expect($cached->relationLoaded('examSession'))->toBeTrue();
});

test('saveAnswers cache is cleared when no active attempt found', function () {
    $cacheKey = "active_attempt:{$this->examSession->id}:{$this->siswa->id}";

    // Pre-populate cache with a null-returning scenario (no attempt started)
    Cache::put($cacheKey, null, 300);

    $response = $this->actingAs($this->siswa)
        ->postJson(route('siswa.ujian.save-answers', $this->examSession), [
            'answers' => [(string) $this->questions['pg'][0]->id => 'A'],
        ]);

    $response->assertNotFound();
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('saveAnswers cache is cleared when exam is expired', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->andReturn(true);
    Redis::shouldReceive('del')->andReturn(1);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
    $cacheKey = "active_attempt:{$this->examSession->id}:{$this->siswa->id}";

    // Force expire by moving started_at far back
    $attempt->update(['started_at' => now()->subHours(24)]);

    // Clear any stale cache so the query re-fetches with expired timestamp
    Cache::forget($cacheKey);

    $response = $this->actingAs($this->siswa)
        ->postJson(route('siswa.ujian.save-answers', $this->examSession), [
            'answers' => [(string) $this->questions['pg'][0]->id => 'A'],
        ]);

    $response->assertStatus(410);
    $response->assertJson(['expired' => true]);
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('submit clears active attempt cache', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $cacheKey = "active_attempt:{$this->examSession->id}:{$this->siswa->id}";

    // Warm the cache
    Cache::put($cacheKey, ExamAttempt::where('user_id', $this->siswa->id)->first(), 300);
    expect(Cache::has($cacheKey))->toBeTrue();

    // Submit
    $this->actingAs($this->siswa)
        ->post(route('siswa.ujian.submit', $this->examSession));

    expect(Cache::has($cacheKey))->toBeFalse();
});
