<?php

declare(strict_types=1);

use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment();
    $this->guru = $env['guru'];
    $this->siswa = $env['siswa'];
    $this->classroom = $env['classroom'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];
});

// ===== New Security Event Types =====

test('log activity accepts screenshot_attempt event type', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $attempt->id,
            'event_type' => 'screenshot_attempt',
            'description' => 'Screenshot blocked',
        ]);

    $response->assertOk();
    $response->assertJson(['logged' => true]);

    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'screenshot_attempt',
    ]);
});

test('log activity accepts devtools_attempt event type', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $attempt->id,
            'event_type' => 'devtools_attempt',
            'description' => 'DevTools shortcut blocked',
        ]);

    $response->assertOk();
    $response->assertJson(['logged' => true]);

    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'devtools_attempt',
    ]);
});

test('log activity accepts devtools_open event type', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $attempt->id,
            'event_type' => 'devtools_open',
            'description' => 'DevTools detected open',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'devtools_open',
    ]);
});

test('log activity accepts print_attempt event type', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $attempt->id,
            'event_type' => 'print_attempt',
            'description' => 'Print blocked',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'print_attempt',
    ]);
});

test('log activity accepts copy_paste_attempt event type', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $attempt->id,
            'event_type' => 'copy_paste_attempt',
            'description' => 'Copy/paste blocked',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'copy_paste_attempt',
    ]);
});

test('log activity accepts keyboard_shortcut event type', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $response = $this->actingAs($this->siswa)
        ->postJson(route('api.exam.log-activity'), [
            'attempt_id' => $attempt->id,
            'event_type' => 'keyboard_shortcut',
            'description' => 'Keyboard shortcut blocked',
        ]);

    $response->assertOk();
    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'keyboard_shortcut',
    ]);
});

test('log activity still accepts existing event types', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    $existingTypes = ['tab_switch', 'fullscreen_exit', 'focus_lost', 'copy_attempt', 'right_click'];

    foreach ($existingTypes as $eventType) {
        $response = $this->actingAs($this->siswa)
            ->postJson(route('api.exam.log-activity'), [
                'attempt_id' => $attempt->id,
                'event_type' => $eventType,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('exam_activity_logs', [
            'exam_attempt_id' => $attempt->id,
            'event_type' => $eventType,
        ]);
    }
});

// ===== Security Hardening Config Toggle =====

test('exam payload includes security_hardening flag', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // Start exam to create an attempt
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    // start() should render ExamInterface directly with the payload
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/ExamInterface')
        ->has('security_hardening')
    );
});

test('exam payload security_hardening defaults to true', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/ExamInterface')
        ->where('security_hardening', true)
    );
});

test('exam payload security_hardening can be toggled via setting', function () {
    // Mock SettingService to return anti_cheat_enabled = false
    $settingService = Mockery::mock(\App\Services\SettingService::class)->makePartial();
    $settingService->shouldReceive('get')
        ->with('anti_cheat_enabled', Mockery::any())
        ->andReturn(false);
    $settingService->shouldReceive('get')
        ->with(Mockery::not('anti_cheat_enabled'), Mockery::any())
        ->passthru();
    app()->instance(\App\Services\SettingService::class, $settingService);

    Redis::shouldReceive('get')->andReturn(null);

    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Ujian/ExamInterface')
        ->where('security_hardening', false)
    );
});
