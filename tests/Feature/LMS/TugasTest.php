<?php

declare(strict_types=1);

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->subject = Subject::factory()->create();
    $this->classroom = Classroom::factory()->create();

    TeachingAssignment::create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->classroom->students()->attach($this->siswa->id);

    $this->assignment = Assignment::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);
});

// ── Guru ─────────────────────────────────────────────────────────────

test('guru can view tugas index', function () {
    $this->actingAs($this->guru)
        ->get(route('guru.tugas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Guru/Tugas/Index'));
});

test('guru can create tugas', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.tugas.store'), [
            'title' => 'Tugas Membuat Program',
            'description' => 'Buat program Hello World',
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'deadline_at' => now()->addDays(7)->format('Y-m-d H:i'),
            'max_score' => 100,
            'allow_late_submission' => false,
            'late_penalty_percent' => 0,
            'submission_type' => 'file_or_text',
            'is_published' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('assignments', [
        'title' => 'Tugas Membuat Program',
        'user_id' => $this->guru->id,
    ]);
});

test('guru can grade submission', function () {
    $submission = AssignmentSubmission::create([
        'assignment_id' => $this->assignment->id,
        'user_id' => $this->siswa->id,
        'content' => 'Jawaban saya',
        'submitted_at' => now(),
        'is_late' => false,
    ]);

    $this->actingAs($this->guru)
        ->put(route('guru.tugas.grade', $submission), [
            'score' => 85,
            'feedback' => 'Bagus sekali!',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('assignment_submissions', [
        'id' => $submission->id,
        'score' => 85,
        'feedback' => 'Bagus sekali!',
    ]);

    expect($submission->fresh()->graded_at)->not->toBeNull();
});

test('guru cannot grade with score above max', function () {
    $submission = AssignmentSubmission::create([
        'assignment_id' => $this->assignment->id,
        'user_id' => $this->siswa->id,
        'content' => 'Jawaban',
        'submitted_at' => now(),
        'is_late' => false,
    ]);

    $this->actingAs($this->guru)
        ->put(route('guru.tugas.grade', $submission), [
            'score' => 150, // above max_score = 100
            'feedback' => null,
        ])
        ->assertInvalid('score');
});

// ── Siswa ─────────────────────────────────────────────────────────────

test('siswa can view tugas index', function () {
    $this->actingAs($this->siswa)
        ->get(route('siswa.tugas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Siswa/Tugas/Index'));
});

test('siswa can submit tugas', function () {
    $this->actingAs($this->siswa)
        ->post(route('siswa.tugas.submit', $this->assignment), [
            'content' => 'Jawaban saya untuk tugas ini.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('assignment_submissions', [
        'assignment_id' => $this->assignment->id,
        'user_id' => $this->siswa->id,
        'content' => '<p>Jawaban saya untuk tugas ini.</p>',
        'is_late' => false,
    ]);
});

test('siswa submission is marked late when past deadline', function () {
    $overdueAssignment = Assignment::factory()->overdue()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'allow_late_submission' => true,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.tugas.submit', $overdueAssignment), [
            'content' => 'Jawaban terlambat.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('assignment_submissions', [
        'assignment_id' => $overdueAssignment->id,
        'user_id' => $this->siswa->id,
        'is_late' => true,
    ]);
});

test('siswa cannot submit after deadline if late not allowed', function () {
    $overdueAssignment = Assignment::factory()->overdue()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'allow_late_submission' => false,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.tugas.submit', $overdueAssignment), [
            'content' => 'Jawaban terlambat.',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('submit');
});

test('siswa cannot re-submit after graded', function () {
    $submission = AssignmentSubmission::create([
        'assignment_id' => $this->assignment->id,
        'user_id' => $this->siswa->id,
        'content' => 'Jawaban awal',
        'submitted_at' => now(),
        'is_late' => false,
        'score' => 90,
        'graded_at' => now(),
        'graded_by' => $this->guru->id,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.tugas.submit', $this->assignment), [
            'content' => 'Jawaban baru (setelah dinilai)',
        ])
        ->assertSessionHasErrors('submit');
});

test('siswa from other classroom cannot submit', function () {
    $otherSiswa = User::factory()->siswa()->create();

    $this->actingAs($otherSiswa)
        ->post(route('siswa.tugas.submit', $this->assignment), [
            'content' => 'Tidak bisa submit.',
        ])
        ->assertForbidden();
});
