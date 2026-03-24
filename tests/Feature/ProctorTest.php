<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Events\ExamForceSubmitted;
use App\Events\ExamTimeExtended;
use App\Events\StudentSubmittedExam;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->otherGuru = User::factory()->guru()->create();

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

    $this->pgQuestion = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $this->pgQuestion->id,
        'label' => 'A',
        'content' => 'Jawaban benar',
        'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $this->pgQuestion->id,
        'label' => 'B',
        'content' => 'Jawaban salah',
        'order' => 1,
    ]);

    $this->examSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'token' => 'ABCDEF',
        'duration_minutes' => 60,
    ]);

    $this->examSession->classrooms()->attach($this->classroom->id);
});

// ===== Proctor Dashboard Access =====

test('guru can view proctor dashboard for their exam', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Ujian/Proctor')
        ->has('exam_session')
        ->has('students')
        ->has('summary')
    );
});

test('guru cannot view proctor dashboard for other guru exam', function () {
    $response = $this->actingAs($this->otherGuru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertForbidden();
});

test('siswa cannot access proctor dashboard', function () {
    $response = $this->actingAs($this->siswa)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertForbidden();
});

test('proctor dashboard shows all assigned students', function () {
    $siswa2 = User::factory()->siswa()->create();
    $this->classroom->students()->attach($siswa2->id);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->has('students', 2)
        ->where('summary.total', 2)
        ->where('summary.not_started', 2)
    );
});

test('proctor dashboard shows correct status for student with attempt', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $this->pgQuestion->id,
        'order' => 1,
    ]);

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $this->pgQuestion->id,
        'answer' => 'A',
        'answered_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->where('summary.in_progress', 1)
        ->where('summary.not_started', 0)
    );
});

// ===== Extend Time =====

test('guru can extend time for student', function () {
    Event::fake([ExamTimeExtended::class]);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subMinutes(30),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $originalStartedAt = $attempt->started_at->copy();

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 15,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $attempt->refresh();
    expect((int) $attempt->started_at->diffInMinutes($originalStartedAt))->toBe(15);

    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'proctor_extend_time',
    ]);

    Event::assertDispatched(ExamTimeExtended::class, function ($event) use ($attempt) {
        return $event->userId === $attempt->user_id
            && $event->additionalMinutes === 15;
    });
});

test('guru cannot extend time for submitted attempt', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subMinutes(30),
        'status' => ExamAttemptStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 15,
        ]);

    $response->assertNotFound();
});

test('other guru cannot extend time on exam they do not own', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->otherGuru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 15,
        ]);

    $response->assertForbidden();
});

// ===== Terminate =====

test('guru can terminate student exam', function () {
    Event::fake([StudentSubmittedExam::class, ExamForceSubmitted::class]);
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.terminate', $this->examSession), [
            'attempt_id' => $attempt->id,
            'reason' => 'Kecurangan terdeteksi',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Graded);
    expect($attempt->is_force_submitted)->toBeTrue();

    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'proctor_terminate',
    ]);

    Event::assertDispatched(StudentSubmittedExam::class);
    Event::assertDispatched(ExamForceSubmitted::class, function ($event) {
        return $event->reason === 'Kecurangan terdeteksi';
    });
});

test('guru cannot terminate already submitted exam', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subMinutes(30),
        'status' => ExamAttemptStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.terminate', $this->examSession), [
            'attempt_id' => $attempt->id,
        ]);

    $response->assertNotFound();
});

// ===== Invalidate Question =====

test('guru can invalidate a question', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $this->pgQuestion->id,
        'answer' => 'B',
        'is_correct' => false,
        'score' => 0,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.invalidate-question', $this->examSession), [
            'question_id' => $this->pgQuestion->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $answer = StudentAnswer::where('exam_attempt_id', $attempt->id)
        ->where('question_id', $this->pgQuestion->id)
        ->first();

    expect($answer->is_correct)->toBeTrue();
    expect((float) $answer->score)->toBe(2.0);

    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'proctor_invalidate_question',
    ]);
});

// ===== Validation =====

test('extend time requires valid minutes', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 0,
        ]);

    $response->assertSessionHasErrors('additional_minutes');
});

test('extend time rejects minutes over 120', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 200,
        ]);

    $response->assertSessionHasErrors('additional_minutes');
});
