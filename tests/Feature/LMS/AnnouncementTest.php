<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->classroom = Classroom::factory()->create();
    $this->subject = Subject::factory()->create();

    TeachingAssignment::create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->classroom->students()->attach($this->siswa->id);
});

test('guru can create announcement for specific classroom', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.pengumuman.store'), [
            'title' => 'Pengumuman Kelas X',
            'content' => 'Ini isi pengumuman.',
            'classroom_id' => $this->classroom->id,
            'is_pinned' => false,
            'published_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect(route('guru.pengumuman.index'));

    $this->assertDatabaseHas('announcements', [
        'title' => 'Pengumuman Kelas X',
        'classroom_id' => $this->classroom->id,
        'user_id' => $this->guru->id,
    ]);
});

test('guru can create broadcast announcement (no classroom)', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.pengumuman.store'), [
            'title' => 'Pengumuman Umum',
            'content' => 'Untuk semua.',
            'classroom_id' => null,
            'is_pinned' => false,
            'published_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('announcements', [
        'title' => 'Pengumuman Umum',
        'classroom_id' => null,
    ]);
});

test('siswa can see announcement for their classroom', function () {
    Announcement::create([
        'user_id' => $this->guru->id,
        'classroom_id' => $this->classroom->id,
        'title' => 'Pengumuman',
        'content' => 'Isi',
        'is_pinned' => false,
        'published_at' => now(),
    ]);

    $this->actingAs($this->siswa)
        ->get(route('siswa.pengumuman.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Siswa/Pengumuman/Index')
            ->has('announcements.data', 1)
        );
});

test('siswa can see broadcast announcements', function () {
    Announcement::create([
        'user_id' => $this->guru->id,
        'classroom_id' => null,
        'title' => 'Broadcast',
        'content' => 'Isi broadcast',
        'is_pinned' => false,
        'published_at' => now(),
    ]);

    $this->actingAs($this->siswa)
        ->get(route('siswa.pengumuman.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('announcements.data', 1));
});

test('siswa cannot see announcement from other classroom', function () {
    $otherClassroom = Classroom::factory()->create();

    Announcement::create([
        'user_id' => $this->guru->id,
        'classroom_id' => $otherClassroom->id,
        'title' => 'Pengumuman Kelas Lain',
        'content' => 'Bukan untuk siswa ini',
        'is_pinned' => false,
        'published_at' => now(),
    ]);

    $this->actingAs($this->siswa)
        ->get(route('siswa.pengumuman.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('announcements.data', 0));
});

test('scheduled announcement not visible before publish time', function () {
    $announcement = Announcement::create([
        'user_id' => $this->guru->id,
        'classroom_id' => $this->classroom->id,
        'title' => 'Pengumuman Masa Depan',
        'content' => 'Belum waktunya.',
        'is_pinned' => false,
        'published_at' => now()->addHour(),
    ]);

    $this->actingAs($this->siswa)
        ->get(route('siswa.pengumuman.index'))
        ->assertInertia(fn ($page) => $page->has('announcements.data', 0));

    $this->actingAs($this->siswa)
        ->get(route('siswa.pengumuman.show', $announcement))
        ->assertNotFound();
});

test('guru can toggle pin', function () {
    $announcement = Announcement::create([
        'user_id' => $this->guru->id,
        'classroom_id' => $this->classroom->id,
        'title' => 'Pin Test',
        'content' => 'Isi',
        'is_pinned' => false,
        'published_at' => now(),
    ]);

    $this->actingAs($this->guru)
        ->post(route('guru.pengumuman.toggle-pin', $announcement));

    expect($announcement->fresh()->is_pinned)->toBeTrue();
});

test('guru can delete announcement', function () {
    $announcement = Announcement::create([
        'user_id' => $this->guru->id,
        'classroom_id' => null,
        'title' => 'Hapus',
        'content' => 'Isi',
        'is_pinned' => false,
        'published_at' => now(),
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.pengumuman.destroy', $announcement))
        ->assertRedirect(route('guru.pengumuman.index'));

    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});
