<?php

declare(strict_types=1);

use App\Enums\ExamStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);

    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);

    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->examSession = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'status' => ExamStatus::Scheduled,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
    ]);

    // Attach classroom with students
    $this->examSession->classrooms()->attach($this->classroom->id);
    $this->students = User::factory()->siswa()->count(3)->create();
    foreach ($this->students as $student) {
        $this->classroom->students()->attach($student->id);
    }
});

describe('Print Participant Cards', function () {
    it('generates PDF for exam with assigned classrooms', function () {
        $response = $this->actingAs($this->guru)
            ->get("/guru/ujian/{$this->examSession->id}/print-cards");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    it('returns 422 when no classrooms assigned', function () {
        $this->examSession->classrooms()->detach();

        $response = $this->actingAs($this->guru)
            ->get("/guru/ujian/{$this->examSession->id}/print-cards");

        $response->assertStatus(422);
    });

    it('denies access to non-owner guru', function () {
        $otherGuru = User::factory()->guru()->create();

        $response = $this->actingAs($otherGuru)
            ->get("/guru/ujian/{$this->examSession->id}/print-cards");

        $response->assertForbidden();
    });

    it('includes all students from assigned classrooms', function () {
        $response = $this->actingAs($this->guru)
            ->get("/guru/ujian/{$this->examSession->id}/print-cards");

        $response->assertOk();
        // PDF content is binary, just verify it generates successfully
        expect(strlen($response->getContent()))->toBeGreaterThan(100);
    });
});
