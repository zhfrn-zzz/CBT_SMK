<?php

declare(strict_types=1);

use App\Models\Classroom;
use App\Models\Material;
use App\Models\MaterialProgress;
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

    // Assign guru to teach subject in classroom
    TeachingAssignment::create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    // Enroll siswa in classroom
    $this->classroom->students()->attach($this->siswa->id);
});

// ── Guru CRUD ────────────────────────────────────────────────────────

test('guru can view materi index page', function () {
    $this->actingAs($this->guru)
        ->get(route('guru.materi.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Guru/Materi/Index'));
});

test('guru can view materi create page', function () {
    $this->actingAs($this->guru)
        ->get(route('guru.materi.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Guru/Materi/Create'));
});

test('guru can create text materi', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.materi.store'), [
            'title' => 'Pengenalan PHP',
            'description' => 'Materi dasar PHP',
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'type' => 'text',
            'text_content' => '<p>Isi materi PHP</p>',
            'topic' => 'Bab 1',
            'order' => 1,
            'is_published' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('materials', [
        'title' => 'Pengenalan PHP',
        'user_id' => $this->guru->id,
        'type' => 'text',
    ]);
});

test('guru can upload file materi', function () {
    $file = UploadedFile::fake()->create('materi.pdf', 1024, 'application/pdf');

    $this->actingAs($this->guru)
        ->post(route('guru.materi.store'), [
            'title' => 'Modul PDF',
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'type' => 'file',
            'file' => $file,
            'is_published' => true,
        ])
        ->assertRedirect();

    $material = Material::where('title', 'Modul PDF')->first();
    expect($material)->not->toBeNull();
    expect($material->file_original_name)->toBe('materi.pdf');
    Storage::assertExists($material->file_path);
});

test('guru cannot create materi for unassigned classroom', function () {
    $otherClassroom = Classroom::factory()->create();

    $this->actingAs($this->guru)
        ->post(route('guru.materi.store'), [
            'title' => 'Materi Ilegal',
            'subject_id' => $this->subject->id,
            'classroom_id' => $otherClassroom->id,
            'type' => 'text',
            'text_content' => 'Isi',
            'is_published' => true,
        ])
        ->assertInvalid('classroom_id');
});

test('guru can update materi', function () {
    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->guru)
        ->put(route('guru.materi.update', $material), [
            'title' => 'Judul Diupdate',
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'type' => 'text',
            'text_content' => 'Konten baru',
            'is_published' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('materials', ['id' => $material->id, 'title' => 'Judul Diupdate']);
});

test('guru can delete materi', function () {
    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.materi.destroy', $material))
        ->assertRedirect(route('guru.materi.index'));

    $this->assertDatabaseMissing('materials', ['id' => $material->id]);
});

test('guru cannot edit materi owned by another guru', function () {
    $otherGuru = User::factory()->guru()->create();
    $material = Material::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->guru)
        ->put(route('guru.materi.update', $material), [
            'title' => 'Hacked',
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'type' => 'text',
            'text_content' => 'Hacked',
            'is_published' => true,
        ])
        ->assertForbidden();
});

// ── Siswa View ───────────────────────────────────────────────────────

test('siswa can view materi index', function () {
    $this->actingAs($this->siswa)
        ->get(route('siswa.materi.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Siswa/Materi/Index'));
});

test('siswa can view published materi', function () {
    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
        'is_published' => true,
    ]);

    $this->actingAs($this->siswa)
        ->get(route('siswa.materi.show', $material))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Siswa/Materi/Show'));
});

test('siswa cannot access draft materi', function () {
    $material = Material::factory()->draft()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->siswa)
        ->get(route('siswa.materi.show', $material))
        ->assertForbidden();
});

test('siswa can mark materi as complete', function () {
    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.materi.complete', $material))
        ->assertRedirect();

    $this->assertDatabaseHas('material_progress', [
        'material_id' => $material->id,
        'user_id' => $this->siswa->id,
        'is_completed' => true,
    ]);
});

test('marking materi complete twice is idempotent', function () {
    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->siswa)->post(route('siswa.materi.complete', $material));
    $this->actingAs($this->siswa)->post(route('siswa.materi.complete', $material));

    $this->assertDatabaseCount('material_progress', 1);
});

test('siswa from another classroom cannot access materi', function () {
    $otherClassroom = Classroom::factory()->create();
    $otherSiswa = User::factory()->siswa()->create();
    $otherClassroom->students()->attach($otherSiswa->id);

    $material = Material::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($otherSiswa)
        ->get(route('siswa.materi.show', $material))
        ->assertForbidden();
});
