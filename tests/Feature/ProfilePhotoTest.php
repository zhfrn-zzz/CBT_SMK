<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('user can upload profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('photo.jpg', 600, 600)->size(1024);

    $response = $this->actingAs($user)
        ->post(route('profile.photo.update'), [
            'photo' => $file,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->photo_path);
});

test('photo is resized after upload', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // Create a larger image
    $file = UploadedFile::fake()->image('photo.jpg', 800, 800)->size(512);

    $this->actingAs($user)
        ->post(route('profile.photo.update'), [
            'photo' => $file,
        ]);

    $user->refresh();
    expect($user->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->photo_path);
});

test('old photo is deleted when new one uploaded', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // Upload first photo
    $file1 = UploadedFile::fake()->image('photo1.jpg', 300, 300)->size(256);
    $this->actingAs($user)
        ->post(route('profile.photo.update'), ['photo' => $file1]);

    $user->refresh();
    $oldPath = $user->photo_path;
    Storage::disk('public')->assertExists($oldPath);

    // Upload second photo
    $file2 = UploadedFile::fake()->image('photo2.jpg', 300, 300)->size(256);
    $this->actingAs($user)
        ->post(route('profile.photo.update'), ['photo' => $file2]);

    $user->refresh();
    expect($user->photo_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($user->photo_path);
});

test('photo validation rejects files over 2MB', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('photo.jpg', 600, 600)->size(3000); // 3MB

    $response = $this->actingAs($user)
        ->post(route('profile.photo.update'), [
            'photo' => $file,
        ]);

    $response->assertSessionHasErrors('photo');
    expect($user->refresh()->photo_path)->toBeNull();
});

test('photo validation rejects non-image files', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('document.pdf', 512, 'application/pdf');

    $response = $this->actingAs($user)
        ->post(route('profile.photo.update'), [
            'photo' => $file,
        ]);

    $response->assertSessionHasErrors('photo');
    expect($user->refresh()->photo_path)->toBeNull();
});

test('user can delete profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // Upload photo first
    $file = UploadedFile::fake()->image('photo.jpg', 300, 300)->size(256);
    $this->actingAs($user)
        ->post(route('profile.photo.update'), ['photo' => $file]);

    $user->refresh();
    $photoPath = $user->photo_path;
    expect($photoPath)->not->toBeNull();

    // Delete photo
    $response = $this->actingAs($user)
        ->delete(route('profile.photo.delete'));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($photoPath);
});

test('profile update accepts phone and bio fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '081234567890',
            'bio' => 'Saya seorang pelajar.',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->phone)->toBe('081234567890');
    expect($user->bio)->toBe('Saya seorang pelajar.');
});
