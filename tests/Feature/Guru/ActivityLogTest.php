<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment();
    $this->guru = $env['guru'];
    $this->siswa = $env['siswa'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];

    // Start and submit exam
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $this->attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // Create some activity logs
    $events = [
        ['event_type' => 'tab_switch', 'description' => 'Tab switch #1'],
        ['event_type' => 'tab_switch', 'description' => 'Tab switch #2'],
        ['event_type' => 'right_click', 'description' => 'Right click detected'],
        ['event_type' => 'fullscreen_exit', 'description' => 'Keluar dari fullscreen'],
        ['event_type' => 'copy_attempt', 'description' => 'Copy attempt detected'],
    ];

    foreach ($events as $event) {
        ExamActivityLog::create([
            'exam_attempt_id' => $this->attempt->id,
            'event_type' => $event['event_type'],
            'description' => $event['description'],
            'created_at' => now(),
        ]);
    }

    // Submit the exam so guru can access grading
    $this->attempt->update([
        'status' => ExamAttemptStatus::Submitted,
        'submitted_at' => now(),
    ]);
});

test('guru can view activity log for a student attempt', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.activity-log', [
            'examSession' => $this->examSession->id,
            'attempt' => $this->attempt->id,
        ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Penilaian/ActivityLog')
        ->has('logs', 5)
        ->has('summary')
        ->where('summary.total', 5)
        ->where('summary.tab_switches', 2)
        ->where('summary.fullscreen_exits', 1)
        ->where('summary.copy_attempts', 1)
        ->where('summary.right_clicks', 1)
        ->where('attempt.user.name', $this->siswa->name)
    );
});

test('activity log shows exam session info', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.activity-log', [
            'examSession' => $this->examSession->id,
            'attempt' => $this->attempt->id,
        ]));

    $response->assertInertia(fn ($page) => $page
        ->where('examSession.id', $this->examSession->id)
        ->where('examSession.name', $this->examSession->name)
    );
});

test('activity log shows student device info', function () {
    $this->attempt->update([
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 Test Browser',
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.activity-log', [
            'examSession' => $this->examSession->id,
            'attempt' => $this->attempt->id,
        ]));

    $response->assertInertia(fn ($page) => $page
        ->where('attempt.ip_address', '192.168.1.100')
        ->where('attempt.user_agent', 'Mozilla/5.0 Test Browser')
    );
});

test('another guru cannot view activity log', function () {
    $otherGuru = User::factory()->guru()->create();

    $response = $this->actingAs($otherGuru)
        ->get(route('guru.grading.activity-log', [
            'examSession' => $this->examSession->id,
            'attempt' => $this->attempt->id,
        ]));

    $response->assertForbidden();
});

test('empty activity log shows zero counts', function () {
    // Clear all logs
    ExamActivityLog::where('exam_attempt_id', $this->attempt->id)->delete();

    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.activity-log', [
            'examSession' => $this->examSession->id,
            'attempt' => $this->attempt->id,
        ]));

    $response->assertInertia(fn ($page) => $page
        ->has('logs', 0)
        ->where('summary.total', 0)
        ->where('summary.tab_switches', 0)
    );
});
