<?php

declare(strict_types=1);

use App\Jobs\CleanupOrphanedFilesJob;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->admin()->create();
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();

    $this->subject = Subject::factory()->create();
    $this->classroom = Classroom::factory()->create();

    TeachingAssignment::create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);
});

// ── Admin Storage Dashboard ──────────────────────────────────────────

test('admin can view storage dashboard', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.storage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Storage/Index')
            ->has('totalUsed')
            ->has('breakdown')
            ->has('topFiles')
            ->has('orphanedFiles')
        );
});

test('admin storage dashboard shows correct breakdown', function () {
    // Create material with file
    Storage::disk('public')->put('materials/test.pdf', 'content');
    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/test.pdf',
        'file_size' => 5000,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.storage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Storage/Index')
            ->where('breakdown.0.category', 'materials')
            ->where('breakdown.0.count', 1)
            ->where('breakdown.0.size', 5000)
        );
});

test('admin can see top 10 largest files', function () {
    Storage::disk('public')->put('materials/big.pdf', str_repeat('x', 10000));
    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/big.pdf',
        'file_size' => 10000,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.storage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('topFiles', 1)
            ->where('topFiles.0.name', 'big.pdf')
            ->where('topFiles.0.size', 10000)
        );
});

test('admin can detect orphaned files', function () {
    // File on disk without DB record = orphan
    Storage::disk('public')->put('materials/orphan.pdf', 'orphan content');

    // File on disk with DB record = not orphan
    Storage::disk('public')->put('materials/valid.pdf', 'valid content');
    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/valid.pdf',
        'file_size' => 100,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.storage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orphanedFiles', 1)
            ->where('orphanedFiles.0.path', 'materials/orphan.pdf')
        );
});

test('admin can scan orphaned files', function () {
    Storage::disk('public')->put('assignments/orphan.docx', 'content');

    $this->actingAs($this->admin)
        ->get(route('admin.storage.scan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Storage/Index')
            ->has('orphanedFiles', 1)
        );
});

test('admin can dispatch cleanup job', function () {
    Queue::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.storage.cleanup'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(CleanupOrphanedFilesJob::class);
});

test('cleanup job deletes orphaned files', function () {
    Storage::disk('public')->put('materials/orphan.pdf', 'orphan content');
    Storage::disk('public')->put('materials/valid.pdf', 'valid content');
    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/valid.pdf',
        'file_size' => 100,
    ]);

    $job = new CleanupOrphanedFilesJob($this->admin->id);
    $job->handle();

    Storage::disk('public')->assertMissing('materials/orphan.pdf');
    Storage::disk('public')->assertExists('materials/valid.pdf');
});

test('non-admin cannot access storage dashboard', function () {
    $this->actingAs($this->guru)
        ->get(route('admin.storage.index'))
        ->assertForbidden();

    $this->actingAs($this->siswa)
        ->get(route('admin.storage.index'))
        ->assertForbidden();
});

test('non-admin cannot dispatch cleanup', function () {
    $this->actingAs($this->guru)
        ->post(route('admin.storage.cleanup'))
        ->assertForbidden();
});

// ── Guru File Manager ────────────────────────────────────────────────

test('guru can view file manager page', function () {
    $this->actingAs($this->guru)
        ->get(route('guru.file-manager.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Guru/FileManager/Index')
            ->has('files')
            ->has('filters')
        );
});

test('guru sees only their own files', function () {
    $otherGuru = User::factory()->guru()->create();

    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/my-file.pdf',
        'file_size' => 1000,
    ]);

    Material::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/other-file.pdf',
        'file_size' => 2000,
    ]);

    $this->actingAs($this->guru)
        ->get(route('guru.file-manager.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('files', 1)
            ->where('files.0.name', 'my-file.pdf')
        );
});

test('guru can filter files by type', function () {
    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/mat.pdf',
        'file_size' => 1000,
    ]);

    Assignment::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'assignments/tugas.docx',
    ]);
    Storage::disk('public')->put('assignments/tugas.docx', 'content');

    // Filter: material only
    $this->actingAs($this->guru)
        ->get(route('guru.file-manager.index', ['type' => 'material']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('files', 1)
            ->where('files.0.type', 'material')
        );

    // Filter: assignment only
    $this->actingAs($this->guru)
        ->get(route('guru.file-manager.index', ['type' => 'assignment']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('files', 1)
            ->where('files.0.type', 'assignment')
        );
});

test('guru can delete unused material file', function () {
    Storage::disk('public')->put('materials/unused.pdf', 'content');
    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/unused.pdf',
        'file_size' => 1000,
        'is_published' => false,
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.file-manager.destroy', ['type' => 'material', 'id' => $material->id]))
        ->assertRedirect()
        ->assertSessionHas('success');

    Storage::disk('public')->assertMissing('materials/unused.pdf');
    $material->refresh();
    expect($material->file_path)->toBeNull();
});

test('guru cannot delete published material file', function () {
    Storage::disk('public')->put('materials/published.pdf', 'content');
    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/published.pdf',
        'file_size' => 1000,
        'is_published' => true,
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.file-manager.destroy', ['type' => 'material', 'id' => $material->id]))
        ->assertRedirect()
        ->assertSessionHas('error');

    Storage::disk('public')->assertExists('materials/published.pdf');
});

test('guru cannot delete assignment file with submissions', function () {
    Storage::disk('public')->put('assignments/with-subs.docx', 'content');
    $assignment = Assignment::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'assignments/with-subs.docx',
        'is_published' => false,
    ]);

    AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'user_id' => $this->siswa->id,
        'submitted_at' => now(),
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.file-manager.destroy', ['type' => 'assignment', 'id' => $assignment->id]))
        ->assertRedirect()
        ->assertSessionHas('error');

    Storage::disk('public')->assertExists('assignments/with-subs.docx');
});

test('guru can delete question media', function () {
    Storage::disk('public')->put('questions/img.png', 'image data');
    $bank = QuestionBank::factory()->create(['user_id' => $this->guru->id, 'subject_id' => $this->subject->id]);
    $question = Question::factory()->create([
        'question_bank_id' => $bank->id,
        'media_path' => 'questions/img.png',
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.file-manager.destroy', ['type' => 'question', 'id' => $question->id]))
        ->assertRedirect()
        ->assertSessionHas('success');

    Storage::disk('public')->assertMissing('questions/img.png');
    $question->refresh();
    expect($question->media_path)->toBeNull();
});

test('guru cannot delete another guru file', function () {
    $otherGuru = User::factory()->guru()->create();
    Storage::disk('public')->put('materials/other.pdf', 'content');
    $material = Material::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/other.pdf',
        'file_size' => 1000,
        'is_published' => false,
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.file-manager.destroy', ['type' => 'material', 'id' => $material->id]))
        ->assertNotFound();

    Storage::disk('public')->assertExists('materials/other.pdf');
});

test('non-guru cannot access file manager', function () {
    $this->actingAs($this->admin)
        ->get(route('guru.file-manager.index'))
        ->assertForbidden();

    $this->actingAs($this->siswa)
        ->get(route('guru.file-manager.index'))
        ->assertForbidden();
});

test('guru can sort files by size', function () {
    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/small.pdf',
        'file_size' => 100,
    ]);

    Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'materials/large.pdf',
        'file_size' => 50000,
    ]);

    $this->actingAs($this->guru)
        ->get(route('guru.file-manager.index', ['sort' => 'size', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('files', 2)
            ->where('files.0.name', 'large.pdf')
            ->where('files.1.name', 'small.pdf')
        );
});

test('guru cannot delete published assignment file', function () {
    Storage::disk('public')->put('assignments/published.docx', 'content');
    $assignment = Assignment::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'file_path' => 'assignments/published.docx',
        'is_published' => true,
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.file-manager.destroy', ['type' => 'assignment', 'id' => $assignment->id]))
        ->assertRedirect()
        ->assertSessionHas('error');

    Storage::disk('public')->assertExists('assignments/published.docx');
});
