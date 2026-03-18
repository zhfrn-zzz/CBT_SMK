<?php

declare(strict_types=1);

use App\Events\DiscussionReplyCreated;
use App\Models\Classroom;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
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

    $this->thread = DiscussionThread::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);
});

// ── Thread CRUD ───────────────────────────────────────────────────────

test('guru can create discussion thread', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.forum.store'), [
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'title' => 'Diskusi Materi 1',
            'content' => 'Isi diskusi',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('discussion_threads', [
        'title' => 'Diskusi Materi 1',
        'user_id' => $this->guru->id,
    ]);
});

test('siswa can create discussion thread', function () {
    $this->actingAs($this->siswa)
        ->post(route('siswa.forum.store'), [
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'title' => 'Pertanyaan Siswa',
            'content' => 'Saya tidak mengerti materi ini.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('discussion_threads', [
        'title' => 'Pertanyaan Siswa',
        'user_id' => $this->siswa->id,
    ]);
});

test('guru can reply to thread', function () {
    Event::fake();

    $this->actingAs($this->guru)
        ->post(route('guru.forum.reply', $this->thread), [
            'content' => 'Balasan guru.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('discussion_replies', [
        'discussion_thread_id' => $this->thread->id,
        'content' => 'Balasan guru.',
    ]);

    Event::assertDispatched(DiscussionReplyCreated::class);
});

test('siswa can reply to thread', function () {
    Event::fake();

    $this->actingAs($this->siswa)
        ->post(route('siswa.forum.reply', $this->thread), [
            'content' => 'Balasan siswa.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('discussion_replies', [
        'discussion_thread_id' => $this->thread->id,
        'user_id' => $this->siswa->id,
    ]);
});

test('reply increments thread reply_count', function () {
    // No Event::fake() — Eloquent model boot events must fire for reply_count to update
    expect($this->thread->reply_count)->toBe(0);

    $this->actingAs($this->siswa)
        ->post(route('siswa.forum.reply', $this->thread), ['content' => 'Balas 1.']);

    expect($this->thread->fresh()->reply_count)->toBe(1);
});

test('cannot reply to locked thread', function () {
    $locked = DiscussionThread::factory()->locked()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->siswa)
        ->post(route('siswa.forum.reply', $locked), ['content' => 'Coba balas.'])
        ->assertSessionHasErrors('reply');
});

test('guru can pin and unpin thread', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.forum.toggle-pin', $this->thread))
        ->assertRedirect();

    expect($this->thread->fresh()->is_pinned)->toBeTrue();

    $this->actingAs($this->guru)
        ->post(route('guru.forum.toggle-pin', $this->thread))
        ->assertRedirect();

    expect($this->thread->fresh()->is_pinned)->toBeFalse();
});

test('guru can lock and unlock thread', function () {
    $this->actingAs($this->guru)
        ->post(route('guru.forum.toggle-lock', $this->thread))
        ->assertRedirect();

    expect($this->thread->fresh()->is_locked)->toBeTrue();
});

test('guru can delete any thread', function () {
    $studentThread = DiscussionThread::factory()->create([
        'user_id' => $this->siswa->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->guru)
        ->delete(route('guru.forum.destroy', $studentThread))
        ->assertRedirect();

    $this->assertDatabaseMissing('discussion_threads', ['id' => $studentThread->id]);
});

test('siswa can only delete own thread', function () {
    $anotherSiswa = User::factory()->siswa()->create();
    $this->classroom->students()->attach($anotherSiswa->id);

    $otherThread = DiscussionThread::factory()->create([
        'user_id' => $anotherSiswa->id,
        'subject_id' => $this->subject->id,
        'classroom_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->siswa)
        ->delete(route('siswa.forum.destroy', $otherThread))
        ->assertForbidden();
});

test('siswa cannot pin thread', function () {
    $this->actingAs($this->siswa)
        ->post(route('guru.forum.toggle-pin', $this->thread))
        ->assertForbidden();
});
