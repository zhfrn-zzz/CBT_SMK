<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use App\Services\Exam\ExamAttemptService;

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\SingleSessionExam::class);

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

    $this->question = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
    ]);

    $this->examSession = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'status' => ExamStatus::Active,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(2),
    ]);

    $this->examSession->classrooms()->attach($this->classroom->id);
});

describe('Watermark in Exam Payload', function () {
    it('includes watermark_enabled in exam payload', function () {
        $attempt = ExamAttempt::factory()->create([
            'exam_session_id' => $this->examSession->id,
            'user_id' => $this->siswa->id,
            'status' => ExamAttemptStatus::InProgress,
            'started_at' => now(),
        ]);

        $service = app(ExamAttemptService::class);
        $payload = $service->buildExamPayload($attempt);

        expect($payload)->toHaveKey('watermark_enabled');
        expect($payload['watermark_enabled'])->toBeBool();
    });

    it('returns watermark_enabled as true when setting is enabled', function () {
        // Create the setting in DB
        \App\Models\Setting::create([
            'group' => 'exam',
            'key' => 'watermark_enabled',
            'value' => 'true',
            'type' => 'boolean',
        ]);

        // Force clear all cache stores to ensure fresh data
        try {
            \Illuminate\Support\Facades\Cache::store('redis')->forget('settings:all');
        } catch (\Exception) {
            // Redis might not be available in tests
        }
        \Illuminate\Support\Facades\Cache::forget('settings:all');

        // Resolve a fresh service instance
        app()->forgetInstance(\App\Services\SettingService::class);

        $attempt = ExamAttempt::factory()->create([
            'exam_session_id' => $this->examSession->id,
            'user_id' => $this->siswa->id,
            'status' => ExamAttemptStatus::InProgress,
            'started_at' => now(),
        ]);

        $service = app(ExamAttemptService::class);
        $payload = $service->buildExamPayload($attempt);

        expect($payload['watermark_enabled'])->toBeTrue();
    });

    it('passes watermark_enabled to ExamInterface page', function () {
        $attempt = ExamAttempt::factory()->create([
            'exam_session_id' => $this->examSession->id,
            'user_id' => $this->siswa->id,
            'status' => ExamAttemptStatus::InProgress,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->siswa)
            ->get("/siswa/ujian/{$this->examSession->id}/exam");

        $response->assertOk();
    });
});
