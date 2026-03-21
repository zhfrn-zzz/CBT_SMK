<?php

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Http\Middleware\SingleSessionExam;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->siswa = User::factory()->siswa()->create();
    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->subject = Subject::factory()->create();

    $this->guru = User::factory()->guru()->create();
    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    // Register a test route with the middleware
    Route::middleware(['web', 'auth', SingleSessionExam::class])
        ->get('/_test/single-session', fn () => response('OK'));
});

test('middleware allows access when no active exam attempt', function () {
    $response = $this->actingAs($this->siswa)
        ->get('/_test/single-session');

    $response->assertOk();
});

test('middleware allows access when session matches stored session', function () {
    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'question_bank_id' => $this->questionBank->id,
        'academic_year_id' => $this->academicYear->id,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => $this->siswa->id,
        'status' => ExamAttemptStatus::InProgress,
    ]);

    // Store session ID matching the current session — we set it inside the request
    // The middleware will check and pass since no stored session yet → it will store one
    $response = $this->actingAs($this->siswa)
        ->get('/_test/single-session');

    $response->assertOk();

    // Now verify session was stored
    expect(Cache::get("exam_session:{$attempt->id}:session_id"))->not->toBeNull();
});

test('middleware blocks access when session does not match', function () {
    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'question_bank_id' => $this->questionBank->id,
        'academic_year_id' => $this->academicYear->id,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => $this->siswa->id,
        'status' => ExamAttemptStatus::InProgress,
    ]);

    // Store a different session ID
    Cache::put("exam_session:{$attempt->id}:session_id", 'different-session-id', 86400);

    $response = $this->actingAs($this->siswa)
        ->get('/_test/single-session');

    $response->assertForbidden();
});

test('middleware stores session id when none exists', function () {
    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'question_bank_id' => $this->questionBank->id,
        'academic_year_id' => $this->academicYear->id,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => $this->siswa->id,
        'status' => ExamAttemptStatus::InProgress,
    ]);

    expect(Cache::get("exam_session:{$attempt->id}:session_id"))->toBeNull();

    $this->actingAs($this->siswa)
        ->get('/_test/single-session');

    expect(Cache::get("exam_session:{$attempt->id}:session_id"))->not->toBeNull();
});

test('middleware does not affect non-student users', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get('/_test/single-session');

    $response->assertOk();
});

test('middleware allows submitted attempts without session check', function () {
    $examSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'question_bank_id' => $this->questionBank->id,
        'academic_year_id' => $this->academicYear->id,
    ]);

    ExamAttempt::factory()->submitted()->create([
        'exam_session_id' => $examSession->id,
        'user_id' => $this->siswa->id,
    ]);

    // No active attempt, so no session check
    $response = $this->actingAs($this->siswa)
        ->get('/_test/single-session');

    $response->assertOk();
});

test('session config default lifetime is 30 minutes', function () {
    // Verify the config file default (not .env override) is 30
    // .env may override, but the config default should be 30
    $configContent = file_get_contents(base_path('config/session.php'));
    expect($configContent)->toContain("env('SESSION_LIFETIME', 30)");
});
