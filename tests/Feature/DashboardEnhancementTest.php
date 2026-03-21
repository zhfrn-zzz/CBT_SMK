<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

// =====================
// Admin Dashboard — todaySection
// =====================

test('admin dashboard returns todaySection with announcements', function () {
    Cache::flush();
    $admin = User::factory()->admin()->create();

    Announcement::factory()->create([
        'user_id' => $admin->id,
        'is_pinned' => true,
        'published_at' => now()->subMinute(),
    ]);
    Announcement::factory()->create([
        'user_id' => $admin->id,
        'published_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('todaySection')
        ->has('todaySection.announcements', 2)
        ->where('todaySection.announcements.0.is_pinned', true)
    );
});

test('admin dashboard returns todaySection with recent audit logs', function () {
    Cache::flush();
    $admin = User::factory()->admin()->create();

    // Clear any audit logs auto-created by the Auditable trait
    AuditLog::query()->delete();

    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'created',
        'description' => 'Created a user',
        'created_at' => now()->subSecond(),
    ]);
    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'updated',
        'description' => 'Updated a setting',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('todaySection.recentAuditLogs', 2)
        ->where('todaySection.recentAuditLogs.0.action', 'updated')
    );
});

test('admin dashboard returns todaySection with active exam alert', function () {
    Cache::flush();
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->guru()->create();

    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
    $academicYear = AcademicYear::factory()->active()->create();
    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    ExamSession::factory()->active()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
        'name' => 'Ujian Matematika',
    ]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('todaySection.activeExamAlert')
        ->where('todaySection.activeExamAlert.count', 1)
        ->where('todaySection.activeExamAlert.names.0', 'Ujian Matematika')
    );
});

test('admin dashboard todaySection empty when no data', function () {
    Cache::flush();
    $admin = User::factory()->admin()->create();

    // Clear any audit logs auto-created by the Auditable trait
    AuditLog::query()->delete();

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('todaySection')
        ->has('todaySection.announcements', 0)
        ->has('todaySection.recentAuditLogs', 0)
        ->where('todaySection.activeExamAlert.count', 0)
    );
});

// =====================
// Guru Dashboard — todaySection
// =====================

test('guru dashboard returns todaySection with announcements', function () {
    Cache::flush();
    $guru = User::factory()->guru()->create();
    $classroom = Classroom::factory()->create();
    $subject = Subject::factory()->create();

    TeachingAssignment::create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
    ]);

    // Broadcast announcement (classroom_id = null) should be visible
    Announcement::factory()->create([
        'user_id' => $guru->id,
        'classroom_id' => null,
        'is_pinned' => true,
        'published_at' => now()->subMinute(),
    ]);

    // Classroom-specific announcement for guru's classroom
    Announcement::factory()->forClassroom($classroom)->create([
        'user_id' => $guru->id,
        'published_at' => now()->subMinutes(2),
    ]);

    $response = $this->actingAs($guru)->get('/guru/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Guru/Dashboard')
        ->has('todaySection')
        ->has('todaySection.announcements', 2)
    );
});

test('guru dashboard returns todaySection with active exams and participant count', function () {
    Cache::flush();
    $guru = User::factory()->guru()->create();
    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
    $academicYear = AcademicYear::factory()->active()->create();
    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
    ]);

    // Create in-progress attempts
    ExamAttempt::factory()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => User::factory()->siswa()->create()->id,
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($guru)->get('/guru/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Guru/Dashboard')
        ->has('todaySection.active_exams', 1)
        ->where('todaySection.active_exams.0.in_progress_count', 1)
    );
});

test('guru dashboard returns todaySection with pending grading data', function () {
    Cache::flush();
    $guru = User::factory()->guru()->create();
    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
    $academicYear = AcademicYear::factory()->active()->create();
    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
    ]);

    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
    ]);

    $esaiQuestion = Question::factory()->esai()->create([
        'question_bank_id' => $questionBank->id,
    ]);

    $attempt = ExamAttempt::factory()->submitted()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => User::factory()->siswa()->create()->id,
    ]);

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $esaiQuestion->id,
        'answer' => 'Jawaban esai siswa',
        'answered_at' => now(),
        'score' => null,
    ]);

    $response = $this->actingAs($guru)->get('/guru/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Guru/Dashboard')
        ->has('todaySection.pending_grading')
        ->where('todaySection.pending_grading.0.subject', $subject->name)
        ->where('todaySection.pending_grading.0.count', 1)
    );
});

test('guru dashboard returns todaySection with today attendance data', function () {
    Cache::flush();
    $guru = User::factory()->guru()->create();
    $classroom = Classroom::factory()->create();
    $subject = Subject::factory()->create();

    TeachingAssignment::create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
    ]);

    Attendance::factory()->create([
        'user_id' => $guru->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'meeting_date' => today(),
        'is_open' => true,
    ]);

    $response = $this->actingAs($guru)->get('/guru/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Guru/Dashboard')
        ->has('todaySection.today_attendance', 1)
        ->where('todaySection.today_attendance.0.is_open', true)
    );
});

test('guru dashboard todaySection empty when no data', function () {
    Cache::flush();
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get('/guru/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Guru/Dashboard')
        ->has('todaySection')
        ->has('todaySection.announcements', 0)
        ->has('todaySection.active_exams', 0)
        ->has('todaySection.pending_grading', 0)
        ->has('todaySection.today_attendance', 0)
    );
});

// =====================
// Siswa Dashboard — todaySection
// =====================

test('siswa dashboard returns todaySection with announcements', function () {
    Cache::flush();
    $siswa = User::factory()->siswa()->create();
    $classroom = Classroom::factory()->create();
    $classroom->students()->attach($siswa->id);
    $guru = User::factory()->guru()->create();

    Announcement::factory()->forClassroom($classroom)->create([
        'user_id' => $guru->id,
        'is_pinned' => true,
        'published_at' => now()->subMinute(),
    ]);

    // Broadcast announcement
    Announcement::factory()->create([
        'user_id' => $guru->id,
        'classroom_id' => null,
        'published_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($siswa)->get('/siswa/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Siswa/Dashboard')
        ->has('todaySection')
        ->has('todaySection.announcements', 2)
    );
});

test('siswa dashboard returns todaySection with upcoming exams this week', function () {
    Cache::flush();
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

    // Scheduled exam within this week
    $examSession = ExamSession::factory()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
        'status' => ExamStatus::Scheduled,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(4),
        'name' => 'Ujian IPA Minggu Ini',
    ]);
    $examSession->classrooms()->attach($classroom->id);

    $response = $this->actingAs($siswa)->get('/siswa/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Siswa/Dashboard')
        ->has('todaySection.upcoming_exams', 1)
        ->where('todaySection.upcoming_exams.0.name', 'Ujian IPA Minggu Ini')
    );
});

test('siswa dashboard returns todaySection with deadline assignments', function () {
    Cache::flush();
    $siswa = User::factory()->siswa()->create();
    $classroom = Classroom::factory()->create();
    $classroom->students()->attach($siswa->id);

    $guru = User::factory()->guru()->create();
    $subject = Subject::factory()->create();

    // Assignment due within 3 days
    Assignment::factory()->create([
        'user_id' => $guru->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'title' => 'Tugas Deadline Dekat',
        'deadline_at' => now()->addDays(2),
        'is_published' => true,
    ]);

    // Assignment due in 10 days — should NOT appear in todaySection
    Assignment::factory()->create([
        'user_id' => $guru->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'deadline_at' => now()->addDays(10),
        'is_published' => true,
    ]);

    $response = $this->actingAs($siswa)->get('/siswa/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Siswa/Dashboard')
        ->has('todaySection.deadline_assignments', 1)
        ->where('todaySection.deadline_assignments.0.title', 'Tugas Deadline Dekat')
    );
});

test('siswa dashboard returns todaySection with new materials', function () {
    Cache::flush();
    $siswa = User::factory()->siswa()->create();
    $classroom = Classroom::factory()->create();
    $classroom->students()->attach($siswa->id);

    $guru = User::factory()->guru()->create();
    $subject = Subject::factory()->create();

    Material::factory()->create([
        'user_id' => $guru->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'title' => 'Materi Baru Minggu Ini',
        'is_published' => true,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($siswa)->get('/siswa/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Siswa/Dashboard')
        ->has('todaySection.new_materials', 1)
        ->where('todaySection.new_materials.0.title', 'Materi Baru Minggu Ini')
    );
});

test('siswa dashboard todaySection empty when no data', function () {
    Cache::flush();
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get('/siswa/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Siswa/Dashboard')
        ->has('todaySection')
        ->has('todaySection.announcements', 0)
        ->has('todaySection.upcoming_exams', 0)
        ->has('todaySection.deadline_assignments', 0)
        ->has('todaySection.new_materials', 0)
    );
});
