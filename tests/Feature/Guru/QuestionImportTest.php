<?php

declare(strict_types=1);

use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->subject = Subject::factory()->create();
    $this->bank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);
});

test('guru can download question template', function () {
    $response = $this->actingAs($this->guru)->get(
        route('guru.bank-soal.soal.template', $this->bank)
    );

    $response->assertOk();
    // Template is an Excel download
    expect($response->headers->get('content-disposition'))->toContain('template_soal');
});

test('question import rejects invalid file types', function () {
    $file = UploadedFile::fake()->create('questions.txt', 100, 'text/plain');

    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.import', $this->bank),
        ['file' => $file]
    );

    $response->assertSessionHasErrors('file');
});

test('question import requires file', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.import', $this->bank),
        []
    );

    $response->assertSessionHasErrors('file');
});

test('guru cannot import to another guru bank', function () {
    $otherGuru = User::factory()->guru()->create();
    $otherBank = QuestionBank::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
    ]);

    $file = UploadedFile::fake()->create('questions.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.import', $otherBank),
        ['file' => $file]
    );

    $response->assertForbidden();
});
