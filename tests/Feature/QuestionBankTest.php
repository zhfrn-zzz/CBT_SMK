<?php

declare(strict_types=1);

use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->subject = Subject::factory()->create();

    // Assign guru ke subject via classroom_subject_teacher
    // (fallback: kalau belum di-assign, controller return semua subjects)
});

test('guru can view bank soal index', function () {
    QuestionBank::factory()->count(3)->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.bank-soal.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Index')
        ->has('questionBanks.data', 3)
    );
});

test('guru can view create bank soal form', function () {
    $response = $this->actingAs($this->guru)->get(route('guru.bank-soal.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Create')
        ->has('subjects')
    );
});

test('guru can create bank soal', function () {
    $response = $this->actingAs($this->guru)->post(route('guru.bank-soal.store'), [
        'name' => 'Bank Soal Baru',
        'subject_id' => $this->subject->id,
        'description' => 'Deskripsi bank soal.',
    ]);

    $response->assertRedirect(route('guru.bank-soal.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('question_banks', [
        'name' => 'Bank Soal Baru',
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);
});

test('guru can view own bank soal detail', function () {
    $bank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.bank-soal.show', $bank));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Show')
        ->has('questionBank')
    );
});

test('guru cannot view other guru bank soal', function () {
    $otherGuru = User::factory()->guru()->create();
    $bank = QuestionBank::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.bank-soal.show', $bank));

    $response->assertForbidden();
});

test('guru can update own bank soal', function () {
    $bank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->put(route('guru.bank-soal.update', $bank), [
        'name' => 'Nama Baru',
        'subject_id' => $this->subject->id,
        'description' => 'Deskripsi baru.',
    ]);

    $response->assertRedirect(route('guru.bank-soal.index'));

    $this->assertDatabaseHas('question_banks', [
        'id' => $bank->id,
        'name' => 'Nama Baru',
    ]);
});

test('guru cannot update other guru bank soal', function () {
    $otherGuru = User::factory()->guru()->create();
    $bank = QuestionBank::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->put(route('guru.bank-soal.update', $bank), [
        'name' => 'Hacked',
        'subject_id' => $this->subject->id,
    ]);

    $response->assertForbidden();
});

test('guru can delete own bank soal', function () {
    $bank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->delete(route('guru.bank-soal.destroy', $bank));

    $response->assertRedirect(route('guru.bank-soal.index'));

    $this->assertDatabaseMissing('question_banks', ['id' => $bank->id]);
});

test('guru cannot delete other guru bank soal', function () {
    $otherGuru = User::factory()->guru()->create();
    $bank = QuestionBank::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->delete(route('guru.bank-soal.destroy', $bank));

    $response->assertForbidden();
});

test('create bank soal requires name', function () {
    $response = $this->actingAs($this->guru)->post(route('guru.bank-soal.store'), [
        'subject_id' => $this->subject->id,
    ]);

    $response->assertSessionHasErrors('name');
});

test('create bank soal requires valid subject', function () {
    $response = $this->actingAs($this->guru)->post(route('guru.bank-soal.store'), [
        'name' => 'Test',
        'subject_id' => 99999,
    ]);

    $response->assertSessionHasErrors('subject_id');
});

test('guru can filter bank soal by search', function () {
    QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'name' => 'UTS Matematika',
    ]);
    QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'name' => 'UAS Fisika',
    ]);

    $response = $this->actingAs($this->guru)->get(route('guru.bank-soal.index', ['search' => 'Matematika']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Index')
        ->has('questionBanks.data', 1)
    );
});
