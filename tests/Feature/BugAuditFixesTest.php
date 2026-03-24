<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSession;
use App\Models\ForumThread;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// =====================================================================
// 1.2, 1.3, 1.4 — GradingController cross-exam validation
// =====================================================================

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();

    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);

    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $this->classroom->students()->attach($this->siswa->id);

    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->sessionA = ExamSession::factory()->completed()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);
    $this->sessionA->classrooms()->attach($this->classroom->id);

    $this->sessionB = ExamSession::factory()->completed()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);
    $this->sessionB->classrooms()->attach($this->classroom->id);

    $this->esaiQuestion = Question::factory()->esai()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 10,
        'order' => 1,
    ]);

    $this->attemptA = ExamAttempt::create([
        'exam_session_id' => $this->sessionA->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subHours(3),
        'submitted_at' => now()->subHours(2),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::Submitted,
        'is_fully_graded' => false,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $this->attemptA->id,
        'question_id' => $this->esaiQuestion->id,
        'order' => 1,
    ]);

    $this->answerA = StudentAnswer::create([
        'exam_attempt_id' => $this->attemptA->id,
        'question_id' => $this->esaiQuestion->id,
        'answer' => 'Jawaban esai.',
        'answered_at' => now()->subHours(3),
    ]);
});

// --- 1.2: manualGrading cross-exam ---

test('[1.2] guru cannot view manual grading for attempt from different exam session', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/grading/{$this->sessionB->id}/attempt/{$this->attemptA->id}");
    $response->assertStatus(404);
});

test('[1.2] guru can view manual grading for attempt from correct exam session', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/grading/{$this->sessionA->id}/attempt/{$this->attemptA->id}");
    $response->assertStatus(200);
});

// --- 1.3: saveGrade cross-exam ---

test('[1.3] guru cannot save grade for answer from different exam session', function () {
    $response = $this->actingAs($this->guru)
        ->post("/guru/grading/{$this->sessionB->id}/answer/{$this->answerA->id}", [
            'score' => 8, 'feedback' => 'Good',
        ]);
    $response->assertStatus(404);
});

test('[1.3] guru can save grade for answer from correct exam session', function () {
    $response = $this->actingAs($this->guru)
        ->post("/guru/grading/{$this->sessionA->id}/answer/{$this->answerA->id}", [
            'score' => 8, 'feedback' => 'Good',
        ]);
    $response->assertRedirect();
    $this->answerA->refresh();
    expect((float) $this->answerA->score)->toBe(8.0);
});

// --- 1.4: activityLog cross-exam ---

test('[1.4] guru cannot view activity log for attempt from different exam session', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/grading/{$this->sessionB->id}/attempt/{$this->attemptA->id}/activity-log");
    $response->assertStatus(404);
});

test('[1.4] guru can view activity log for attempt from correct exam session', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/grading/{$this->sessionA->id}/attempt/{$this->attemptA->id}/activity-log");
    $response->assertStatus(200);
});

// =====================================================================
// 1.1 — ForumController togglePin & toggleLock authorization
// =====================================================================

test('[1.1] siswa cannot toggle pin (controller-level auth)', function () {
    $siswa = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create(['is_pinned' => false]);
    $response = $this->actingAs($siswa)->post("/forum/{$thread->id}/toggle-pin");
    $response->assertStatus(403);
    $thread->refresh();
    expect($thread->is_pinned)->toBeFalse();
});

test('[1.1] siswa cannot toggle lock (controller-level auth)', function () {
    $siswa = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create(['is_locked' => false]);
    $response = $this->actingAs($siswa)->post("/forum/{$thread->id}/toggle-lock");
    $response->assertStatus(403);
    $thread->refresh();
    expect($thread->is_locked)->toBeFalse();
});

test('[1.1] admin can toggle pin', function () {
    $admin = User::factory()->admin()->create();
    $thread = ForumThread::factory()->create(['is_pinned' => false]);
    $response = $this->actingAs($admin)->post("/forum/{$thread->id}/toggle-pin");
    $response->assertRedirect();
    $thread->refresh();
    expect($thread->is_pinned)->toBeTrue();
});

test('[1.1] guru can toggle lock', function () {
    $guru = User::factory()->guru()->create();
    $thread = ForumThread::factory()->create(['is_locked' => false]);
    $response = $this->actingAs($guru)->post("/forum/{$thread->id}/toggle-lock");
    $response->assertRedirect();
    $thread->refresh();
    expect($thread->is_locked)->toBeTrue();
});

// =====================================================================
// 2.1 — Double-submit pessimistic locking
// =====================================================================

test('[2.1] submitExam handles already-submitted attempt gracefully', function () {
    $guru = User::factory()->guru()->create();
    $siswa2 = User::factory()->siswa()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
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

    $attempt = ExamAttempt::create([
        'exam_session_id' => $examSession->id,
        'user_id' => $siswa2->id,
        'started_at' => now()->subMinutes(30),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    $service = app(\App\Services\Exam\ExamAttemptService::class);
    $service->submitExam($attempt);

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->submitted_at)->not->toBeNull();

    // Second submit should be a no-op
    $service->submitExam($attempt);
    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
});

test('[2.1] submitExam code uses lockForUpdate within transaction', function () {
    $code = file_get_contents(app_path('Services/Exam/ExamAttemptService.php'));

    // Verify the code uses pessimistic locking pattern
    expect($code)->toContain('DB::transaction(');
    expect($code)->toContain('lockForUpdate()');
    expect($code)->toContain("->where('status', ExamAttemptStatus::InProgress)");
});

// =====================================================================
// 7.2 — ProctorService terminate pessimistic locking
// =====================================================================

test('[7.2] proctor terminate does not re-submit already-submitted exam', function () {
    $guru = User::factory()->guru()->create();
    $siswa2 = User::factory()->siswa()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
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

    $attempt = ExamAttempt::create([
        'exam_session_id' => $examSession->id,
        'user_id' => $siswa2->id,
        'started_at' => now()->subMinutes(30),
        'submitted_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::Submitted,
    ]);

    $proctorService = app(\App\Services\Exam\ProctorService::class);
    $proctorService->terminate($attempt, $guru, 'Test reason');

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
});

// =====================================================================
// 2.2 — SingleSessionExam TOCTOU (Cache::add atomic)
// =====================================================================

test('[2.2] SingleSessionExam blocks when different session exists', function () {
    $siswa2 = User::factory()->siswa()->create();
    $guru2 = User::factory()->guru()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $subject = Subject::factory()->create();
    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru2->id,
        'subject_id' => $subject->id,
    ]);
    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $guru2->id,
        'subject_id' => $subject->id,
        'question_bank_id' => $questionBank->id,
        'academic_year_id' => $academicYear->id,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => $siswa2->id,
        'status' => ExamAttemptStatus::InProgress,
    ]);

    Cache::put("exam_session:{$attempt->id}:session_id", 'different-device-session-id', 86400);

    Route::middleware(['web', 'auth', \App\Http\Middleware\SingleSessionExam::class])
        ->get('/_test/atomic-session-check', fn () => response('OK'));

    $response = $this->actingAs($siswa2)->get('/_test/atomic-session-check');
    $response->assertForbidden();
});

test('[2.2] SingleSessionExam stores session atomically when none exists', function () {
    $siswa2 = User::factory()->siswa()->create();
    $guru2 = User::factory()->guru()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $subject = Subject::factory()->create();
    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru2->id,
        'subject_id' => $subject->id,
    ]);
    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $guru2->id,
        'subject_id' => $subject->id,
        'question_bank_id' => $questionBank->id,
        'academic_year_id' => $academicYear->id,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => $siswa2->id,
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $cacheKey = "exam_session:{$attempt->id}:session_id";
    expect(Cache::get($cacheKey))->toBeNull();

    Route::middleware(['web', 'auth', \App\Http\Middleware\SingleSessionExam::class])
        ->get('/_test/atomic-session-store', fn () => response('OK'));

    $response = $this->actingAs($siswa2)->get('/_test/atomic-session-store');
    $response->assertOk();
    expect(Cache::get($cacheKey))->not->toBeNull();
});

test('[2.2] middleware code uses Cache::add for atomic set-if-not-exists', function () {
    $code = file_get_contents(app_path('Http/Middleware/SingleSessionExam.php'));
    expect($code)->toContain('Cache::add(');
});

// =====================================================================
// 4.1, 4.2, 4.3 — .env.example Redis defaults
// =====================================================================

test('[4.1-4.3] .env.example has Redis defaults', function () {
    $envContent = file_get_contents(base_path('.env.example'));
    expect($envContent)->toContain('SESSION_DRIVER=redis');
    expect($envContent)->toContain('QUEUE_CONNECTION=redis');
    expect($envContent)->toContain('CACHE_STORE=redis');
});

// =====================================================================
// 7.3 — Timezone Asia/Jakarta
// =====================================================================

test('[7.3] config/app.php uses Asia/Jakarta default', function () {
    $configContent = file_get_contents(base_path('config/app.php'));
    expect($configContent)->toContain("env('APP_TIMEZONE', 'Asia/Jakarta')");
});

test('[7.3] .env.example has APP_TIMEZONE=Asia/Jakarta', function () {
    $envContent = file_get_contents(base_path('.env.example'));
    expect($envContent)->toContain('APP_TIMEZONE=Asia/Jakarta');
});

// =====================================================================
// 4.7 — Migration file 2026 date prefix
// =====================================================================

test('[4.7] performance indexes migration has 2026 date prefix', function () {
    $files = glob(database_path('migrations/*add_performance_indexes*'));
    expect($files)->not->toBeEmpty();
    $filename = basename($files[0]);
    expect($filename)->toStartWith('2026_');
});

// =====================================================================
// 4.5 — Fortify login handles NULL email
// =====================================================================

test('[4.5] user with null email can log in via username', function () {
    $user = User::factory()->create([
        'email' => null,
        'username' => 'test_user_no_email',
    ]);

    $response = $this->post(route('login'), [
        'username' => 'test_user_no_email',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect($user->dashboardRoute());
});

test('[4.5] fortify uses username not email', function () {
    expect(config('fortify.username'))->toBe('username');
});

// =====================================================================
// 7.1 — Device lock uses encrypted session + cache
// =====================================================================

test('[7.1] ExamController stores lock in both cache and session', function () {
    $code = file_get_contents(app_path('Http/Controllers/Siswa/ExamController.php'));
    expect($code)->toContain('Cache::put("exam_session:{$attempt->id}:session_id"');
    expect($code)->toContain('session()->put("exam_lock:{$attempt->id}"');
});

test('[7.1] SingleSessionExam middleware has session-based recovery fallback', function () {
    $code = file_get_contents(app_path('Http/Middleware/SingleSessionExam.php'));
    expect($code)->toContain('session()->get($sessionLockKey)');
    expect($code)->toContain('Cache::add(');
});

test('[7.1] exam start stores session lock in cache', function () {
    $guru2 = User::factory()->guru()->create();
    $siswa2 = User::factory()->siswa()->create();
    $academicYear = AcademicYear::factory()->active()->create();
    $department = Department::factory()->create();
    $subject = Subject::factory()->create(['department_id' => $department->id]);
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $academicYear->id,
        'department_id' => $department->id,
    ]);
    $classroom->students()->attach($siswa2->id);

    $questionBank = QuestionBank::factory()->create([
        'user_id' => $guru2->id,
        'subject_id' => $subject->id,
    ]);
    $pgQuestion = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $pgQuestion->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $pgQuestion->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $guru2->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $questionBank->id,
        'token' => 'XYZABC',
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $examSession->classrooms()->attach($classroom->id);

    $response = $this->actingAs($siswa2)
        ->withoutMiddleware(\App\Http\Middleware\SingleSessionExam::class)
        ->get(route('siswa.ujian.start', $examSession));

    $attempt = ExamAttempt::where('exam_session_id', $examSession->id)
        ->where('user_id', $siswa2->id)
        ->first();

    expect($attempt)->not->toBeNull();
    expect(Cache::get("exam_session:{$attempt->id}:session_id"))->not->toBeNull();
});
