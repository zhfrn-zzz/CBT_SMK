<?php

declare(strict_types=1);

use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;

// === Forum Index ===

it('allows any authenticated user to view forum index', function () {
    $user = User::factory()->siswa()->create();
    ForumThread::factory()->count(3)->create();

    $response = $this->actingAs($user)->get('/forum');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Forum/Index')
        ->has('threads.data', 3)
        ->has('categories')
    );
});

it('paginates forum threads at 20 per page', function () {
    $user = User::factory()->admin()->create();
    $category = ForumCategory::factory()->create();
    ForumThread::factory()->count(25)->create(['forum_category_id' => $category->id]);

    $response = $this->actingAs($user)->get('/forum');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('threads.per_page', 20)
        ->where('threads.total', 25)
    );
});

it('filters threads by category slug', function () {
    $user = User::factory()->guru()->create();
    $cat1 = ForumCategory::factory()->create(['name' => 'Akademik', 'slug' => 'akademik']);
    $cat2 = ForumCategory::factory()->create(['name' => 'Teknologi', 'slug' => 'teknologi']);
    ForumThread::factory()->count(2)->create(['forum_category_id' => $cat1->id]);
    ForumThread::factory()->count(3)->create(['forum_category_id' => $cat2->id]);

    $response = $this->actingAs($user)->get('/forum?category=akademik');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->where('threads.total', 2));
});

it('searches threads by title', function () {
    $user = User::factory()->siswa()->create();
    ForumThread::factory()->create(['title' => 'Belajar Laravel']);
    ForumThread::factory()->create(['title' => 'Diskusi Vue.js']);

    $response = $this->actingAs($user)->get('/forum?search=Laravel');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->where('threads.total', 1));
});

// === Forum Create ===

it('shows create thread form', function () {
    $user = User::factory()->siswa()->create();

    $response = $this->actingAs($user)->get('/forum/create');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Forum/Create')
        ->has('categories')
    );
});

it('allows any user to create a thread', function () {
    $user = User::factory()->siswa()->create();
    $category = ForumCategory::factory()->create();

    $response = $this->actingAs($user)->post('/forum', [
        'title' => 'Thread Baru',
        'content' => 'Ini konten thread baru.',
        'forum_category_id' => $category->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('forum_threads', [
        'title' => 'Thread Baru',
        'user_id' => $user->id,
        'forum_category_id' => $category->id,
    ]);
});

it('validates required fields when creating a thread', function () {
    $user = User::factory()->siswa()->create();

    $response = $this->actingAs($user)->post('/forum', [
        'title' => '',
        'content' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'content']);
});

// === Forum Show ===

it('shows thread detail with replies', function () {
    $user = User::factory()->guru()->create();
    $thread = ForumThread::factory()->create();
    ForumReply::factory()->count(3)->create(['forum_thread_id' => $thread->id]);

    $response = $this->actingAs($user)->get("/forum/{$thread->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Forum/Show')
        ->has('thread')
        ->has('replies.data', 3)
        ->has('can')
    );
});

it('increments view count when viewing a thread', function () {
    $user = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create(['view_count' => 5]);

    $this->actingAs($user)->get("/forum/{$thread->id}");

    $thread->refresh();
    expect($thread->view_count)->toBe(6);
});

// === Forum Reply ===

it('allows user to reply to a thread', function () {
    $user = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create();

    $response = $this->actingAs($user)->post("/forum/{$thread->id}/reply", [
        'content' => 'Ini balasan saya.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('forum_replies', [
        'forum_thread_id' => $thread->id,
        'user_id' => $user->id,
        'content' => 'Ini balasan saya.',
    ]);
});

it('cannot reply to a locked thread', function () {
    $user = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->locked()->create();

    $response = $this->actingAs($user)->post("/forum/{$thread->id}/reply", [
        'content' => 'Ini balasan.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseCount('forum_replies', 0);
});

// === Forum Delete ===

it('allows admin to delete any thread', function () {
    $admin = User::factory()->admin()->create();
    $thread = ForumThread::factory()->create();

    $response = $this->actingAs($admin)->delete("/forum/{$thread->id}");

    $response->assertRedirect('/forum');
    $this->assertDatabaseMissing('forum_threads', ['id' => $thread->id]);
});

it('allows guru to delete any thread', function () {
    $guru = User::factory()->guru()->create();
    $thread = ForumThread::factory()->create();

    $response = $this->actingAs($guru)->delete("/forum/{$thread->id}");

    $response->assertRedirect('/forum');
    $this->assertDatabaseMissing('forum_threads', ['id' => $thread->id]);
});

it('allows siswa to delete own thread', function () {
    $siswa = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create(['user_id' => $siswa->id]);

    $response = $this->actingAs($siswa)->delete("/forum/{$thread->id}");

    $response->assertRedirect('/forum');
    $this->assertDatabaseMissing('forum_threads', ['id' => $thread->id]);
});

it('forbids siswa from deleting other threads', function () {
    $siswa = User::factory()->siswa()->create();
    $other = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($siswa)->delete("/forum/{$thread->id}");

    $response->assertStatus(403);
});

// === Delete Reply ===

it('allows admin to delete any reply', function () {
    $admin = User::factory()->admin()->create();
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->create(['forum_thread_id' => $thread->id]);

    $response = $this->actingAs($admin)->delete("/forum/reply/{$reply->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('forum_replies', ['id' => $reply->id]);
});

it('allows siswa to delete own reply', function () {
    $siswa = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $siswa->id]);

    $response = $this->actingAs($siswa)->delete("/forum/reply/{$reply->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('forum_replies', ['id' => $reply->id]);
});

it('forbids siswa from deleting other replies', function () {
    $siswa = User::factory()->siswa()->create();
    $other = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $other->id]);

    $response = $this->actingAs($siswa)->delete("/forum/reply/{$reply->id}");

    $response->assertStatus(403);
});

// === Pin / Lock ===

it('allows admin to toggle pin', function () {
    $admin = User::factory()->admin()->create();
    $thread = ForumThread::factory()->create(['is_pinned' => false]);

    $response = $this->actingAs($admin)->post("/forum/{$thread->id}/toggle-pin");

    $response->assertRedirect();
    $thread->refresh();
    expect($thread->is_pinned)->toBeTrue();
});

it('allows guru to toggle lock', function () {
    $guru = User::factory()->guru()->create();
    $thread = ForumThread::factory()->create(['is_locked' => false]);

    $response = $this->actingAs($guru)->post("/forum/{$thread->id}/toggle-lock");

    $response->assertRedirect();
    $thread->refresh();
    expect($thread->is_locked)->toBeTrue();
});

it('forbids siswa from toggling pin', function () {
    $siswa = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create();

    $response = $this->actingAs($siswa)->post("/forum/{$thread->id}/toggle-pin");

    $response->assertStatus(403);
});

it('forbids siswa from toggling lock', function () {
    $siswa = User::factory()->siswa()->create();
    $thread = ForumThread::factory()->create();

    $response = $this->actingAs($siswa)->post("/forum/{$thread->id}/toggle-lock");

    $response->assertStatus(403);
});

// === Guests cannot access forum ===

it('redirects guests to login', function () {
    $response = $this->get('/forum');
    $response->assertRedirect('/login');
});
