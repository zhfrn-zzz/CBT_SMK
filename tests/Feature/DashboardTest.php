<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected to role dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect($user->dashboardRoute());
});

// =====================
// Admin Dashboard
// =====================

test('admin dashboard shows real stats', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->guru()->count(3)->create();
    User::factory()->siswa()->count(10)->create();

    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
    $academicYear = AcademicYear::factory()->active()->create();
    $questionBank = QuestionBank::factory()->create([
        'subject_id' => $subject->id,
        'user_id' => $guru->first()->id,
    ]);

    ExamSession::factory()->active()->create([
        'subject_id' => $subject->id,
        'user_id' => $guru->first()->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
    ]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->has('stats')
        ->where('stats.total_guru', 3)
        ->where('stats.total_siswa', 10)
        ->where('stats.active_exams', 1)
    );
});

// =====================
// Guru Dashboard
// =====================

test('guru dashboard shows real stats', function () {
    $guru = User::factory()->guru()->create();
    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
    $academicYear = AcademicYear::factory()->active()->create();

    $classroom = Classroom::factory()->create([
        'academic_year_id' => $academicYear->id,
        'department_id' => $department->id,
    ]);
    $classroom->teachers()->attach($guru->id, ['subject_id' => $subject->id]);

    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    ExamSession::factory()->scheduled()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
    ]);

    $response = $this->actingAs($guru)->get('/guru/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Dashboard')
        ->has('stats')
        ->where('stats.class_count', 1)
        ->where('stats.upcoming_exams', 1)
    );
});

// =====================
// Siswa Dashboard
// =====================

test('siswa dashboard shows real stats', function () {
    $siswa = User::factory()->siswa()->create();
    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
    $academicYear = AcademicYear::factory()->active()->create();

    $classroom = Classroom::factory()->create([
        'academic_year_id' => $academicYear->id,
        'department_id' => $department->id,
    ]);
    $classroom->students()->attach($siswa->id);

    $guru = User::factory()->guru()->create();
    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    $scheduledSession = ExamSession::factory()->scheduled()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
    ]);
    $scheduledSession->classrooms()->attach($classroom->id);

    $completedSession = ExamSession::factory()->completed()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
        'is_results_published' => true,
        'kkm' => 75.00,
    ]);
    $completedSession->classrooms()->attach($classroom->id);

    ExamAttempt::create([
        'exam_session_id' => $completedSession->id,
        'user_id' => $siswa->id,
        'started_at' => now()->subDays(2),
        'submitted_at' => now()->subDays(2)->addHour(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::Graded,
        'score' => 85.00,
        'is_fully_graded' => true,
    ]);

    $response = $this->actingAs($siswa)->get('/siswa/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Dashboard')
        ->has('stats')
        ->where('stats.upcoming_exams', 1)
        ->where('stats.completed_exams', 1)
        ->where('stats.latest_score', 85)
        ->has('recentResults', 1)
    );
});

test('siswa dashboard shows null latest_score when no published results', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get('/siswa/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Siswa/Dashboard')
        ->where('stats.latest_score', null)
    );
});
