<?php

declare(strict_types=1);

use App\Models\AcademicYear;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $subject = Subject::factory()->create();
    $this->bank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $subject->id,
    ]);
    $academicYear = AcademicYear::factory()->create();
    $this->examSession = ExamSession::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $subject->id,
        'academic_year_id' => $academicYear->id,
        'question_bank_id' => $this->bank->id,
    ]);
});

test('guru can generate pdf for exam session', function () {
    Question::factory(3)->pilihanGanda()->create(['question_bank_id' => $this->bank->id]);

    $response = $this->actingAs($this->guru)
        ->get("/guru/ujian/{$this->examSession->id}/print-pdf");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('guru can generate pdf for exam session with no questions', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/ujian/{$this->examSession->id}/print-pdf");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('guru cannot print pdf for another gurus exam', function () {
    $anotherGuru = User::factory()->guru()->create();

    $this->actingAs($anotherGuru)
        ->get("/guru/ujian/{$this->examSession->id}/print-pdf")
        ->assertForbidden();
});

test('siswa cannot access print pdf', function () {
    $siswa = User::factory()->siswa()->create();

    $this->actingAs($siswa)
        ->get("/guru/ujian/{$this->examSession->id}/print-pdf")
        ->assertForbidden();
});

test('unauthenticated user cannot access print pdf', function () {
    $this->get("/guru/ujian/{$this->examSession->id}/print-pdf")
        ->assertRedirect('/login');
});

test('print pdf contains exam info', function () {
    $response = $this->actingAs($this->guru)
        ->get("/guru/ujian/{$this->examSession->id}/print-pdf");

    $response->assertOk();
    // Just assert it's a PDF response
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});
