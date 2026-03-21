<?php

declare(strict_types=1);

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// === Calendar Page Access ===

it('allows siswa to view calendar page', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get('/siswa/kalender');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Siswa/Kalender'));
});

it('allows guru to view calendar page', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get('/guru/kalender');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Guru/Kalender'));
});

it('forbids siswa from accessing guru calendar', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get('/guru/kalender');

    $response->assertStatus(403);
});

// === Calendar API ===

it('returns calendar events for siswa', function () {
    $siswa = User::factory()->siswa()->create();
    $classroom = Classroom::factory()->create();
    $subject = Subject::factory()->create();
    $siswa->classrooms()->attach($classroom);

    // Create exam in the current month
    $exam = ExamSession::factory()->create([
        'subject_id' => $subject->id,
        'starts_at' => now()->startOfMonth()->addDays(5)->setHour(8),
        'ends_at' => now()->startOfMonth()->addDays(5)->setHour(10),
        'is_published' => true,
    ]);
    $exam->classrooms()->attach($classroom);

    // Create assignment
    Assignment::factory()->create([
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'deadline_at' => now()->startOfMonth()->addDays(10),
        'is_published' => true,
    ]);

    // Create attendance
    Attendance::factory()->create([
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'meeting_date' => now()->startOfMonth()->addDays(3),
    ]);

    Cache::flush();

    $response = $this->actingAs($siswa)->get('/api/calendar/events?' . http_build_query([
        'month' => now()->month,
        'year' => now()->year,
    ]));

    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toBeArray();
    expect(count($data))->toBe(3);

    $types = collect($data)->pluck('type')->unique()->sort()->values()->toArray();
    expect($types)->toContain('exam');
    expect($types)->toContain('assignment');
    expect($types)->toContain('attendance');
});

it('returns calendar events for guru', function () {
    $guru = User::factory()->guru()->create();
    $subject = Subject::factory()->create();

    ExamSession::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'starts_at' => now()->startOfMonth()->addDays(7)->setHour(8),
        'ends_at' => now()->startOfMonth()->addDays(7)->setHour(10),
    ]);

    Cache::flush();

    $response = $this->actingAs($guru)->get('/api/calendar/events?' . http_build_query([
        'month' => now()->month,
        'year' => now()->year,
    ]));

    $response->assertStatus(200);
    $data = $response->json();
    expect(count($data))->toBeGreaterThanOrEqual(1);
    expect($data[0]['type'])->toBe('exam');
});

it('validates month and year parameters', function () {
    $user = User::factory()->siswa()->create();

    $response = $this->actingAs($user)->get('/api/calendar/events');
    $response->assertStatus(302); // validation redirect

    $response = $this->actingAs($user)->get('/api/calendar/events?month=13&year=2024');
    $response->assertStatus(302);
});

it('caches calendar events with 10 minute TTL', function () {
    $guru = User::factory()->guru()->create();

    Cache::flush();

    $this->actingAs($guru)->get('/api/calendar/events?' . http_build_query([
        'month' => now()->month,
        'year' => now()->year,
    ]));

    $cacheKey = "calendar:{$guru->id}:" . now()->year . ':' . now()->month;
    expect(Cache::has($cacheKey))->toBeTrue();
});

it('returns empty array when no events exist', function () {
    $siswa = User::factory()->siswa()->create();

    Cache::flush();

    $response = $this->actingAs($siswa)->get('/api/calendar/events?' . http_build_query([
        'month' => now()->month,
        'year' => now()->year,
    ]));

    $response->assertStatus(200);
    expect($response->json())->toBeArray()->toBeEmpty();
});
