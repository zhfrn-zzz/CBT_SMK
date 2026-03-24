<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

beforeEach(function () {
    $env = $this->createExamEnvironment([
        'is_device_lock_enabled' => true,
    ]);
    $this->guru = $env['guru'];
    $this->siswa = $env['siswa'];
    $this->questions = $env['questions'];
    $this->examSession = $env['examSession'];
});

test('device lock captures IP and user agent on first start', function () {
    Redis::shouldReceive('get')->andReturn(null);

    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession), [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    expect($attempt)->not->toBeNull();
    expect($attempt->ip_address)->not->toBeNull();
    expect($attempt->user_agent)->not->toBeNull();
});

test('device lock allows resume from same IP', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // Manually set the IP to match what the test will send
    $attempt->update(['ip_address' => '127.0.0.1']);

    // Resume from same IP — should succeed
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertOk();
});

test('device lock blocks resume from different IP', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // Set a different IP to simulate device change (clear fingerprint to test IP-only fallback)
    $attempt->update(['ip_address' => '10.0.0.99', 'device_fingerprint' => null]);

    // Resume — should be blocked
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertRedirect(route('siswa.ujian.index'));
    $response->assertSessionHas('error');
});

test('device lock disabled allows different IP', function () {
    // Disable device lock
    $this->examSession->update(['is_device_lock_enabled' => false]);

    Redis::shouldReceive('get')->andReturn(null);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // Set a different IP
    $attempt->update(['ip_address' => '10.0.0.99']);

    // Resume — should work even with different IP
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession));

    $response->assertOk();
});

test('device lock blocks resume from different user agent', function () {
    Redis::shouldReceive('get')->andReturn(null);

    // Start exam
    $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.start', $this->examSession));

    $attempt = ExamAttempt::where('user_id', $this->siswa->id)->first();

    // Set matching IP but different user agent (clear fingerprint to test UA-only fallback)
    $attempt->update([
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0',
        'device_fingerprint' => null,
    ]);

    // Resume with a different user agent
    $response = $this->actingAs($this->siswa)
        ->get(route('siswa.ujian.exam', $this->examSession), [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Mobile',
        ]);

    $response->assertRedirect(route('siswa.ujian.index'));
    $response->assertSessionHas('error');
});
