<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\ExamSession;
use App\Models\User;

// --- Task 5.1: Public Home Page ---

it('shows public home page with school info', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->has('school', fn ($school) => $school
            ->has('name')
            ->has('address')
            ->has('logo_path')
            ->has('tagline')
        )
        ->has('announcements')
        ->has('examSchedules')
    );
});

it('shows public announcements on home page', function () {
    $guru = User::factory()->guru()->create();

    // Public announcement
    Announcement::factory()->for($guru, 'user')->public()->create([
        'title' => 'Pengumuman Publik Test',
        'published_at' => now()->subHour(),
    ]);

    // Non-public announcement (should NOT appear)
    Announcement::factory()->for($guru, 'user')->create([
        'title' => 'Pengumuman Internal',
        'published_at' => now()->subHour(),
    ]);

    // Unpublished public announcement (future date — should NOT appear)
    Announcement::factory()->for($guru, 'user')->public()->create([
        'title' => 'Pengumuman Masa Depan',
        'published_at' => now()->addDay(),
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->has('announcements', 1)
        ->where('announcements.0.title', 'Pengumuman Publik Test')
    );
});

it('shows max 5 public announcements', function () {
    $guru = User::factory()->guru()->create();

    Announcement::factory()->for($guru, 'user')->public()->count(7)->create([
        'published_at' => now()->subHour(),
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('announcements', 5)
    );
});

it('shows upcoming exam schedules on home page', function () {
    $guru = User::factory()->guru()->create();
    $academicYear = \App\Models\AcademicYear::factory()->active()->create();
    $department = \App\Models\Department::factory()->create();
    $subject = \App\Models\Subject::factory()->create(['department_id' => $department->id]);
    $questionBank = \App\Models\QuestionBank::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    ExamSession::factory()->create([
        'name' => 'Ujian Publik Test',
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
        'is_published' => true,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
    ]);

    // Past exam (should NOT appear)
    ExamSession::factory()->create([
        'name' => 'Ujian Lama',
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
        'is_published' => true,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('examSchedules', 1)
        ->where('examSchedules.0.name', 'Ujian Publik Test')
    );
});

it('shows placeholder when no public announcements exist', function () {
    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('announcements', 0)
    );
});

it('shows dashboard button when authenticated', function () {
    $user = User::factory()->siswa()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->where('auth.user.id', $user->id)
    );
});

it('loads school config values correctly', function () {
    // Seed settings for the test
    \App\Models\Setting::updateOrCreate(
        ['key' => 'school_name'],
        ['group' => 'general', 'value' => 'SMK Test', 'type' => 'string'],
    );
    \App\Models\Setting::updateOrCreate(
        ['key' => 'school_tagline'],
        ['group' => 'general', 'value' => 'Tagline Test', 'type' => 'string'],
    );

    // Clear cache to ensure fresh values
    app(\App\Services\SettingService::class)->clearCache();

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('school.name', 'SMK Test')
        ->where('school.tagline', 'Tagline Test')
    );
});
