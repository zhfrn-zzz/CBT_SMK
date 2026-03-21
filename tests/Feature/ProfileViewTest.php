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
use App\Models\TeachingAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user can view own profile', function () {
    $user = User::factory()->siswa()->create();

    $response = $this->actingAs($user)->get(route('profile.show', $user));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->has('user')
        ->where('user.id', $user->id)
        ->where('user.name', $user->name)
    );
});

test('admin can view any user profile', function () {
    $admin = User::factory()->admin()->create();
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($admin)->get(route('profile.show', $siswa));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->where('user.id', $siswa->id)
    );
});

test('guru can view student in their class', function () {
    $guru = User::factory()->guru()->create();
    $siswa = User::factory()->siswa()->create();
    $classroom = Classroom::factory()->create();
    $subject = Subject::factory()->create();

    TeachingAssignment::create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
    ]);
    $classroom->students()->attach($siswa->id);

    $response = $this->actingAs($guru)->get(route('profile.show', $siswa));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->where('user.id', $siswa->id)
    );
});

test('guru cannot view student not in their class', function () {
    $guru = User::factory()->guru()->create();
    $siswa = User::factory()->siswa()->create();

    $classroomA = Classroom::factory()->create();
    $classroomB = Classroom::factory()->create();
    $subject = Subject::factory()->create();

    TeachingAssignment::create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroomA->id,
    ]);

    // Siswa is in classroom B, guru teaches classroom A
    $classroomB->students()->attach($siswa->id);

    $response = $this->actingAs($guru)->get(route('profile.show', $siswa));

    $response->assertForbidden();
});

test('siswa can view classmate profile', function () {
    $siswa1 = User::factory()->siswa()->create();
    $siswa2 = User::factory()->siswa()->create();
    $classroom = Classroom::factory()->create();

    $classroom->students()->attach([$siswa1->id, $siswa2->id]);

    $response = $this->actingAs($siswa1)->get(route('profile.show', $siswa2));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->where('user.id', $siswa2->id)
    );
});

test('siswa can view their teacher profile', function () {
    $siswa = User::factory()->siswa()->create();
    $guru = User::factory()->guru()->create();
    $classroom = Classroom::factory()->create();
    $subject = Subject::factory()->create();

    $classroom->students()->attach($siswa->id);
    TeachingAssignment::create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
    ]);

    $response = $this->actingAs($siswa)->get(route('profile.show', $guru));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->where('user.id', $guru->id)
    );
});

test('siswa cannot view student from different class', function () {
    $siswa1 = User::factory()->siswa()->create();
    $siswa2 = User::factory()->siswa()->create();

    $classroomA = Classroom::factory()->create();
    $classroomB = Classroom::factory()->create();

    $classroomA->students()->attach($siswa1->id);
    $classroomB->students()->attach($siswa2->id);

    $response = $this->actingAs($siswa1)->get(route('profile.show', $siswa2));

    $response->assertForbidden();
});

test('siswa cannot view unrelated guru', function () {
    $siswa = User::factory()->siswa()->create();
    $guru = User::factory()->guru()->create();

    $classroomA = Classroom::factory()->create();
    $classroomB = Classroom::factory()->create();
    $subject = Subject::factory()->create();

    $classroomA->students()->attach($siswa->id);
    TeachingAssignment::create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroomB->id,
    ]);

    $response = $this->actingAs($siswa)->get(route('profile.show', $guru));

    $response->assertForbidden();
});

test('profile shows siswa exam results and attendance', function () {
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

    $examSession = ExamSession::factory()->completed()->create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
        'is_results_published' => true,
        'kkm' => 75.00,
    ]);

    ExamAttempt::create([
        'exam_session_id' => $examSession->id,
        'user_id' => $siswa->id,
        'started_at' => now()->subDays(2),
        'submitted_at' => now()->subDays(2)->addHour(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::Graded,
        'score' => 90.00,
        'is_fully_graded' => true,
    ]);

    $response = $this->actingAs($siswa)->get(route('profile.show', $siswa));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->has('siswa')
        ->has('siswa.classrooms', 1)
        ->has('siswa.exam_results', 1)
        ->where('siswa.exam_results.0.score', 90)
        ->where('siswa.exam_results.0.pass_status', 'lulus')
        ->has('siswa.attendance')
    );
});

test('profile shows guru subjects and classrooms', function () {
    $guru = User::factory()->guru()->create();
    $classroom = Classroom::factory()->create();
    $subject = Subject::factory()->create();

    TeachingAssignment::create([
        'user_id' => $guru->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
    ]);

    $response = $this->actingAs($guru)->get(route('profile.show', $guru));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->has('guru')
        ->has('guru.subjects', 1)
        ->where('guru.subjects.0.name', $subject->name)
        ->has('guru.classrooms', 1)
        ->where('guru.classrooms.0.name', $classroom->name)
    );
});

test('guest cannot access profile page', function () {
    $user = User::factory()->create();

    $response = $this->get(route('profile.show', $user));

    $response->assertRedirect(route('login'));
});
