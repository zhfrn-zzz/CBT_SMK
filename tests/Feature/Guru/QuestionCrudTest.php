<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->subject = Subject::factory()->create();
    $this->bank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);
});

// ── Create PG Question ──────────────────────────────────────────────

test('guru can view create question form', function () {
    $response = $this->actingAs($this->guru)->get(
        route('guru.bank-soal.soal.create', $this->bank)
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Soal/Create')
    );
});

test('guru can create a pilihan ganda question', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::PilihanGanda->value,
            'content' => '<p>Apa ibu kota Indonesia?</p>',
            'points' => 2,
            'explanation' => 'Jakarta adalah ibu kota Indonesia',
            'options' => [
                ['label' => 'A', 'content' => 'Jakarta', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Bandung', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Surabaya', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Semarang', 'is_correct' => false],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('questions', [
        'question_bank_id' => $this->bank->id,
        'type' => QuestionType::PilihanGanda->value,
        'points' => 2,
    ]);

    $question = Question::where('question_bank_id', $this->bank->id)->first();
    expect($question->options)->toHaveCount(4);
    expect($question->options->where('is_correct', true)->count())->toBe(1);
});

// ── Create Benar/Salah Question ─────────────────────────────────────

test('guru can create a benar salah question', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::BenarSalah->value,
            'content' => '<p>Indonesia terletak di Asia Tenggara</p>',
            'points' => 1,
            'options' => [
                ['label' => 'A', 'content' => 'Benar', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Salah', 'is_correct' => false],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $question = Question::where('question_bank_id', $this->bank->id)->first();
    expect($question->type)->toBe(QuestionType::BenarSalah);
    expect($question->options)->toHaveCount(2);
});

// ── Create Esai Question ────────────────────────────────────────────

test('guru can create an esai question', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::Esai->value,
            'content' => '<p>Jelaskan proses fotosintesis</p>',
            'points' => 10,
            'explanation' => 'Fotosintesis adalah proses pembuatan makanan oleh tumbuhan.',
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('questions', [
        'question_bank_id' => $this->bank->id,
        'type' => QuestionType::Esai->value,
        'points' => 10,
    ]);
});

// ── Validation ──────────────────────────────────────────────────────

test('create question requires content', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::PilihanGanda->value,
            'points' => 2,
            'options' => [
                ['label' => 'A', 'content' => 'Opt A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Opt B', 'is_correct' => false],
            ],
        ]
    );

    $response->assertSessionHasErrors('content');
});

test('PG question requires exactly one correct option', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::PilihanGanda->value,
            'content' => '<p>Test</p>',
            'points' => 2,
            'options' => [
                ['label' => 'A', 'content' => 'Opt A', 'is_correct' => false],
                ['label' => 'B', 'content' => 'Opt B', 'is_correct' => false],
            ],
        ]
    );

    $response->assertSessionHasErrors('options');
});

test('PG question rejects multiple correct options', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::PilihanGanda->value,
            'content' => '<p>Test</p>',
            'points' => 2,
            'options' => [
                ['label' => 'A', 'content' => 'Opt A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Opt B', 'is_correct' => true],
            ],
        ]
    );

    $response->assertSessionHasErrors('options');
});

test('create question requires valid type', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => 'invalid_type',
            'content' => '<p>Test</p>',
            'points' => 2,
        ]
    );

    $response->assertSessionHasErrors('type');
});

test('create question requires positive points', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::Esai->value,
            'content' => '<p>Test</p>',
            'points' => 0,
        ]
    );

    $response->assertSessionHasErrors('points');
});

// ── Edit / Update ───────────────────────────────────────────────────

test('guru can view edit question form', function () {
    $question = Question::factory()->create([
        'question_bank_id' => $this->bank->id,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $question->id, 'label' => 'A']);
    QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'B']);

    $response = $this->actingAs($this->guru)->get(
        route('guru.bank-soal.soal.edit', [$this->bank, $question])
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Soal/Edit')
    );
});

test('guru can update a question', function () {
    $question = Question::factory()->create([
        'question_bank_id' => $this->bank->id,
        'content' => '<p>Old content</p>',
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $question->id, 'label' => 'A']);
    QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'B']);

    $response = $this->actingAs($this->guru)->put(
        route('guru.bank-soal.soal.update', [$this->bank, $question]),
        [
            'type' => QuestionType::PilihanGanda->value,
            'content' => '<p>Updated content</p>',
            'points' => 5,
            'options' => [
                ['label' => 'A', 'content' => 'New A', 'is_correct' => false],
                ['label' => 'B', 'content' => 'New B', 'is_correct' => true],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $question->refresh();
    expect($question->content)->toBe('<p>Updated content</p>');
    expect((float) $question->points)->toBe(5.0);
});

// ── Delete ──────────────────────────────────────────────────────────

test('guru can delete a question', function () {
    $question = Question::factory()->create([
        'question_bank_id' => $this->bank->id,
    ]);

    $response = $this->actingAs($this->guru)->delete(
        route('guru.bank-soal.soal.destroy', [$this->bank, $question])
    );

    $response->assertRedirect();

    $this->assertDatabaseMissing('questions', ['id' => $question->id]);
});

// ── Image Upload ────────────────────────────────────────────────────

test('guru can upload an image for question content', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('question.jpg', 800, 600);

    $response = $this->actingAs($this->guru)->post(
        route('guru.soal.upload-image'),
        ['image' => $file]
    );

    $response->assertOk();
    $response->assertJsonStructure(['url']);
});

test('image upload rejects non-image files', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->guru)->post(
        route('guru.soal.upload-image'),
        ['image' => $file]
    );

    $response->assertSessionHasErrors('image');
});

// ── Ownership ───────────────────────────────────────────────────────

test('guru cannot create question in another guru bank', function () {
    $otherGuru = User::factory()->guru()->create();
    $otherBank = QuestionBank::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $otherBank),
        [
            'type' => QuestionType::Esai->value,
            'content' => '<p>Test</p>',
            'points' => 5,
        ]
    );

    $response->assertForbidden();
});

test('guru cannot delete question in another guru bank', function () {
    $otherGuru = User::factory()->guru()->create();
    $otherBank = QuestionBank::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
    ]);
    $question = Question::factory()->create([
        'question_bank_id' => $otherBank->id,
    ]);

    $response = $this->actingAs($this->guru)->delete(
        route('guru.bank-soal.soal.destroy', [$otherBank, $question])
    );

    $response->assertForbidden();
});
