<?php

declare(strict_types=1);

use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;

// === Admin Forum Category CRUD ===

it('allows admin to view forum categories', function () {
    $admin = User::factory()->admin()->create();
    ForumCategory::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get('/admin/forum-categories');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/ForumCategories/Index')
        ->has('categories', 3)
    );
});

it('allows admin to create a category', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/admin/forum-categories', [
        'name' => 'Akademik',
        'description' => 'Diskusi akademik',
        'color' => '#3b82f6',
        'order' => 1,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('forum_categories', [
        'name' => 'Akademik',
        'slug' => 'akademik',
    ]);
});

it('allows admin to update a category', function () {
    $admin = User::factory()->admin()->create();
    $category = ForumCategory::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($admin)->put("/admin/forum-categories/{$category->id}", [
        'name' => 'New Name',
        'description' => 'Updated desc',
        'color' => '#ef4444',
        'order' => 2,
        'is_active' => false,
    ]);

    $response->assertRedirect();
    $category->refresh();
    expect($category->name)->toBe('New Name');
    expect($category->is_active)->toBeFalse();
});

it('allows admin to delete a category and nullify threads', function () {
    $admin = User::factory()->admin()->create();
    $category = ForumCategory::factory()->create();
    $thread = ForumThread::factory()->create(['forum_category_id' => $category->id]);

    $response = $this->actingAs($admin)->delete("/admin/forum-categories/{$category->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('forum_categories', ['id' => $category->id]);
    $thread->refresh();
    expect($thread->forum_category_id)->toBeNull();
});

it('forbids non-admin from accessing category management', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get('/admin/forum-categories');
    $response->assertStatus(403);

    $siswa = User::factory()->siswa()->create();
    $response = $this->actingAs($siswa)->get('/admin/forum-categories');
    $response->assertStatus(403);
});
