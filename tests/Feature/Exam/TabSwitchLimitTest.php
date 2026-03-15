<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment([
        'max_tab_switches' => 3,
    ]);
    $this->guru = $env['guru'];
    $this->siswa = $env['siswa'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];

    // Start exam to create attempt
    Redis::shouldReceive('get')->andReturn(null);
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $this->attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();
});

test('tab switch returns standard warning when below limit', function () {
    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $this->attempt->id,
            'event_type' => 'tab_switch',
            'description' => 'Tab switch #1',
        ]);

    $response->assertOk();
    $response->assertJson([
        'logged' => true,
        'tab_switch_count' => 1,
        'max_tab_switches' => 3,
        'warning_level' => 'standard',
    ]);
});

test('tab switch returns final warning one before limit', function () {
    // Create 1 existing tab switch log
    ExamActivityLog::create([
        'exam_attempt_id' => $this->attempt->id,
        'event_type' => 'tab_switch',
        'description' => 'Tab switch #1',
        'created_at' => now(),
    ]);

    // Second tab switch should trigger final warning (2 out of 3, since limit-1 = 2)
    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $this->attempt->id,
            'event_type' => 'tab_switch',
            'description' => 'Tab switch #2',
        ]);

    $response->assertOk();
    $response->assertJson([
        'logged' => true,
        'tab_switch_count' => 2,
        'max_tab_switches' => 3,
        'warning_level' => 'final',
    ]);
});

test('tab switch auto-submits when limit reached', function () {
    Redis::shouldReceive('del')->andReturn(1);

    // Create 2 existing tab switch logs
    for ($i = 1; $i <= 2; $i++) {
        ExamActivityLog::create([
            'exam_attempt_id' => $this->attempt->id,
            'event_type' => 'tab_switch',
            'description' => "Tab switch #{$i}",
            'created_at' => now(),
        ]);
    }

    // Third tab switch should trigger auto-submit
    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $this->attempt->id,
            'event_type' => 'tab_switch',
            'description' => 'Tab switch #3',
        ]);

    $response->assertOk();
    $response->assertJson([
        'logged' => true,
        'auto_submitted' => true,
    ]);

    $this->attempt->refresh();
    expect($this->attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($this->attempt->is_force_submitted)->toBeTrue();
});

test('non-tab-switch events do not trigger limit', function () {
    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $this->attempt->id,
            'event_type' => 'right_click',
            'description' => 'Right click detected',
        ]);

    $response->assertOk();
    $response->assertJson(['logged' => true]);
    // Should NOT have tab_switch_count or warning_level
    expect($response->json())->not->toHaveKey('tab_switch_count');
});

test('no limit when max_tab_switches is null', function () {
    // Set no limit
    $this->examSession->update(['max_tab_switches' => null]);

    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $this->attempt->id,
            'event_type' => 'tab_switch',
            'description' => 'Tab switch #1',
        ]);

    $response->assertOk();
    $response->assertJson(['logged' => true]);
    // Should NOT have warning_level or auto_submitted
    expect($response->json())->not->toHaveKey('warning_level');
    expect($response->json())->not->toHaveKey('auto_submitted');
});
