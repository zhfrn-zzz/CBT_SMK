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

// ── Double submit prevention ────────────────────────────────────────

test('siswa cannot submit exam twice', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $env = $this->createExamEnvironment();

    $this->actingAs($env['siswa']);

    // Start the exam
    $this->get(route('siswa.ujian.start', $env['examSession']));

    // Submit
    $response1 = $this->post(route('siswa.ujian.submit', $env['examSession']));
    $response1->assertRedirect();

    // Try to submit again
    $response2 = $this->post(route('siswa.ujian.submit', $env['examSession']));
    $response2->assertRedirect(route('siswa.ujian.index'));

    // Only one submitted attempt
    expect(ExamAttempt::where('user_id', $env['siswa']->id)
        ->where('status', ExamAttemptStatus::Submitted)
        ->count())->toBe(1);
});

// ── Save after submit ───────────────────────────────────────────────

test('siswa cannot save answers after submitting', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    $env = $this->createExamEnvironment();

    $this->actingAs($env['siswa']);

    $this->get(route('siswa.ujian.start', $env['examSession']));
    $this->post(route('siswa.ujian.submit', $env['examSession']));

    $response = $this->postJson(route('siswa.ujian.save-answers', $env['examSession']), [
        'answers' => ['1' => 'A'],
        'flagged_questions' => [],
    ]);

    // After submit, the attempt is no longer active — expect 404 or similar rejection
    expect($response->status())->toBeIn([404, 410, 422]);
});

// ── Concurrent save is idempotent ───────────────────────────────────

test('multiple save requests overwrite each other safely', function () {
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('setex')->andReturn(true);

    $env = $this->createExamEnvironment();

    $this->actingAs($env['siswa']);
    $this->get(route('siswa.ujian.start', $env['examSession']));

    $questions = $env['examSession']->questionBank->questions;
    $firstQ = $questions->first();

    // First save
    $response1 = $this->postJson(route('siswa.ujian.save-answers', $env['examSession']), [
        'answers' => [$firstQ->id => 'A'],
        'flagged_questions' => [],
    ]);
    $response1->assertOk();
    $response1->assertJson(['saved' => true]);

    // Second save with different answer
    $response2 = $this->postJson(route('siswa.ujian.save-answers', $env['examSession']), [
        'answers' => [$firstQ->id => 'B'],
        'flagged_questions' => [],
    ]);
    $response2->assertOk();
    $response2->assertJson(['saved' => true]);
});

// ── Role access on exam routes ──────────────────────────────────────

test('admin cannot access siswa exam routes', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('siswa.ujian.index'));

    $response->assertForbidden();
});

test('guru cannot access siswa nilai routes', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('siswa.nilai.index'));

    $response->assertForbidden();
});

test('siswa cannot access guru grading routes', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get(route('guru.grading.index'));

    $response->assertForbidden();
});

test('siswa cannot access guru ujian routes', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get(route('guru.ujian.index'));

    $response->assertForbidden();
});

// ── Timer edge case: exam started right at deadline ─────────────────

test('exam session that just ended rejects new starts', function () {
    $env = $this->createExamEnvironment([
        'starts_at' => now()->subHours(3),
        'ends_at' => now()->subMinute(),
    ]);

    $this->actingAs($env['siswa']);

    $response = $this->get(route('siswa.ujian.start', $env['examSession']));

    $response->assertRedirect(route('siswa.ujian.index'));
});
