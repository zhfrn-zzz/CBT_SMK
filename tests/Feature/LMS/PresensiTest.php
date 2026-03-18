<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->subject = Subject::factory()->create();
    $this->classroom = Classroom::factory()->create();

    TeachingAssignment::create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->classroom->students()->attach($this->siswa->id);
});

// ── Open Session ──────────────────────────────────────────────────────

test('guru can open attendance session', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.presensi.store'), [
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'meeting_date' => today()->toDateString(),
            'meeting_number' => 1,
            'duration_minutes' => 30,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('attendances', [
        'classroom_id' => $this->classroom->id,
        'user_id' => $this->guru->id,
        'is_open' => true,
        'meeting_number' => 1,
    ]);
});

test('attendance session has a 6-digit access code', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.presensi.store'), [
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'meeting_date' => today()->toDateString(),
            'meeting_number' => 1,
            'duration_minutes' => 30,
        ]);

    $attendance = Attendance::first();
    expect(strlen($attendance->access_code))->toBe(6);
    expect(ctype_digit($attendance->access_code))->toBeTrue();
});

test('cannot open duplicate session for same class subject date', function () {
    Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'meeting_date' => today(),
        'meeting_number' => 1,
    ]);

    $this->actingAs($this->guru)
        ->post(route('guru.presensi.store'), [
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'meeting_date' => today()->toDateString(),
            'meeting_number' => 2,
            'duration_minutes' => 30,
        ])
        ->assertSessionHasErrors('meeting_date');
});

// ── Student Check-In ──────────────────────────────────────────────────

test('siswa can check in with correct code', function () {
    $attendance = Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'access_code' => '123456',
        'code_expires_at' => now()->addMinutes(30),
        'is_open' => true,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.presensi.check-in'), ['code' => '123456'])
        ->assertRedirect();

    $this->assertDatabaseHas('attendance_records', [
        'attendance_id' => $attendance->id,
        'user_id' => $this->siswa->id,
        'status' => AttendanceStatus::Hadir->value,
    ]);
});

test('siswa cannot check in with wrong code', function () {
    $attendance = Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'access_code' => '999999',
        'code_expires_at' => now()->addMinutes(30),
        'is_open' => true,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.presensi.check-in'), ['code' => '123456'])
        ->assertRedirect()
        ->assertSessionHasErrors('code');
});

test('siswa cannot check in with expired code', function () {
    $attendance = Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'access_code' => '123456',
        'code_expires_at' => now()->subMinute(),
        'is_open' => true,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.presensi.check-in'), ['code' => '123456'])
        ->assertSessionHasErrors('code');
});

test('siswa cannot check in to closed session', function () {
    $attendance = Attendance::factory()->closed()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'access_code' => '123456',
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.presensi.check-in'), ['code' => '123456'])
        ->assertSessionHasErrors('code');
});

test('siswa cannot check in twice', function () {
    $attendance = Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'access_code' => '123456',
        'code_expires_at' => now()->addMinutes(30),
        'is_open' => true,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.presensi.check-in'), ['code' => '123456']);

    $this->actingAs($this->siswa)
        ->post(route('siswa.presensi.check-in'), ['code' => '123456'])
        ->assertSessionHasErrors('code');
});

// ── Close Session ─────────────────────────────────────────────────────

test('closing session marks absent students as alfa', function () {
    $siswa2 = User::factory()->siswa()->create();
    $this->classroom->students()->attach($siswa2->id);

    $attendance = Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'access_code' => '111111',
        'code_expires_at' => now()->addMinutes(30),
        'is_open' => true,
    ]);

    // Only $this->siswa checks in
    AttendanceRecord::create([
        'attendance_id' => $attendance->id,
        'user_id' => $this->siswa->id,
        'status' => AttendanceStatus::Hadir,
        'checked_in_at' => now(),
    ]);

    $this->actingAs($this->guru)
        ->post(route('guru.presensi.close', $attendance))
        ->assertRedirect();

    // siswa2 should be set to alfa
    $this->assertDatabaseHas('attendance_records', [
        'attendance_id' => $attendance->id,
        'user_id' => $siswa2->id,
        'status' => AttendanceStatus::Alfa->value,
    ]);

    expect($attendance->fresh()->is_open)->toBeFalse();
});

// ── Manual Override ───────────────────────────────────────────────────

test('guru can manually set student status', function () {
    $attendance = Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'is_open' => true,
    ]);

    $this->actingAs($this->guru)
        ->put(route('guru.presensi.update-status', $attendance), [
            'records' => [
                ['user_id' => $this->siswa->id, 'status' => 'izin', 'note' => 'Sakit demam'],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('attendance_records', [
        'attendance_id' => $attendance->id,
        'user_id' => $this->siswa->id,
        'status' => 'izin',
        'note' => 'Sakit demam',
    ]);
});

// ── Regenerate Code ───────────────────────────────────────────────────

test('guru can regenerate access code', function () {
    $attendance = Attendance::factory()->create([
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->guru->id,
        'access_code' => '000000',
        'is_open' => true,
    ]);

    $this->actingAs($this->guru)
        ->post(route('guru.presensi.regenerate-code', $attendance), [
            'duration_minutes' => 15,
        ])
        ->assertRedirect();

    expect($attendance->fresh()->access_code)->not->toBe('000000');
});
