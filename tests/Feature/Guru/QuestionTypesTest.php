<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionKeyword;
use App\Models\QuestionMatchingPair;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->subject = Subject::factory()->create();
    $this->bank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);
});

// ── Isian Singkat ──────────────────────────────────────────────────

test('guru can create an isian singkat question with keywords', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::IsianSingkat->value,
            'content' => '<p>Apa ibu kota Indonesia?</p>',
            'points' => 2,
            'keywords' => ['Jakarta', 'jakarta', 'DKI Jakarta'],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $question = Question::where('question_bank_id', $this->bank->id)->first();
    expect($question->type)->toBe(QuestionType::IsianSingkat);
    expect($question->keywords)->toHaveCount(3);
    expect($question->keywords->pluck('keyword')->toArray())
        ->toContain('Jakarta', 'jakarta', 'DKI Jakarta');
});

test('isian singkat requires at least one keyword', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::IsianSingkat->value,
            'content' => '<p>Test</p>',
            'points' => 2,
            'keywords' => [],
        ]
    );

    $response->assertSessionHasErrors('keywords');
});

test('guru can update an isian singkat question keywords', function () {
    $question = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->bank->id,
    ]);
    $question->keywords()->create(['keyword' => 'old keyword']);

    $response = $this->actingAs($this->guru)->put(
        route('guru.bank-soal.soal.update', [$this->bank, $question]),
        [
            'type' => QuestionType::IsianSingkat->value,
            'content' => '<p>Updated</p>',
            'points' => 3,
            'keywords' => ['new keyword 1', 'new keyword 2'],
        ]
    );

    $response->assertRedirect();
    $question->refresh();

    expect($question->keywords)->toHaveCount(2);
    expect($question->keywords->pluck('keyword')->toArray())
        ->toContain('new keyword 1', 'new keyword 2');
    // Old keyword should be gone
    $this->assertDatabaseMissing('question_keywords', ['keyword' => 'old keyword']);
});

// ── Menjodohkan ────────────────────────────────────────────────────

test('guru can create a menjodohkan question with matching pairs', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::Menjodohkan->value,
            'content' => '<p>Cocokkan negara dengan ibu kotanya</p>',
            'points' => 4,
            'matching_pairs' => [
                ['premise' => 'Indonesia', 'response' => 'Jakarta'],
                ['premise' => 'Malaysia', 'response' => 'Kuala Lumpur'],
                ['premise' => 'Thailand', 'response' => 'Bangkok'],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $question = Question::where('question_bank_id', $this->bank->id)->first();
    expect($question->type)->toBe(QuestionType::Menjodohkan);
    expect($question->matchingPairs)->toHaveCount(3);
    expect($question->matchingPairs[0]->premise)->toBe('Indonesia');
    expect($question->matchingPairs[0]->response)->toBe('Jakarta');
});

test('menjodohkan requires at least 2 pairs', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::Menjodohkan->value,
            'content' => '<p>Test</p>',
            'points' => 2,
            'matching_pairs' => [
                ['premise' => 'Only one', 'response' => 'Pair'],
            ],
        ]
    );

    $response->assertSessionHasErrors('matching_pairs');
});

test('menjodohkan requires premise and response in each pair', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::Menjodohkan->value,
            'content' => '<p>Test</p>',
            'points' => 2,
            'matching_pairs' => [
                ['premise' => 'Has premise', 'response' => ''],
                ['premise' => '', 'response' => 'Has response'],
            ],
        ]
    );

    $response->assertSessionHasErrors([
        'matching_pairs.0.response',
        'matching_pairs.1.premise',
    ]);
});

test('guru can update a menjodohkan question pairs', function () {
    $question = Question::factory()->menjodohkan()->create([
        'question_bank_id' => $this->bank->id,
    ]);
    $question->matchingPairs()->create(['premise' => 'Old P', 'response' => 'Old R', 'order' => 0]);
    $question->matchingPairs()->create(['premise' => 'Old P2', 'response' => 'Old R2', 'order' => 1]);

    $response = $this->actingAs($this->guru)->put(
        route('guru.bank-soal.soal.update', [$this->bank, $question]),
        [
            'type' => QuestionType::Menjodohkan->value,
            'content' => '<p>Updated</p>',
            'points' => 5,
            'matching_pairs' => [
                ['premise' => 'New P1', 'response' => 'New R1'],
                ['premise' => 'New P2', 'response' => 'New R2'],
                ['premise' => 'New P3', 'response' => 'New R3'],
            ],
        ]
    );

    $response->assertRedirect();
    $question->refresh();

    expect($question->matchingPairs)->toHaveCount(3);
    $this->assertDatabaseMissing('question_matching_pairs', ['premise' => 'Old P']);
});

// ── Multiple Answer ────────────────────────────────────────────────

test('guru can create a multiple answer question', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::MultipleAnswer->value,
            'content' => '<p>Pilih bilangan prima</p>',
            'points' => 3,
            'options' => [
                ['label' => 'A', 'content' => '2', 'is_correct' => true],
                ['label' => 'B', 'content' => '4', 'is_correct' => false],
                ['label' => 'C', 'content' => '5', 'is_correct' => true],
                ['label' => 'D', 'content' => '9', 'is_correct' => false],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $question = Question::where('question_bank_id', $this->bank->id)->first();
    expect($question->type)->toBe(QuestionType::MultipleAnswer);
    expect($question->options)->toHaveCount(4);
    expect($question->options->where('is_correct', true)->count())->toBe(2);
});

test('multiple answer requires at least one correct option', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::MultipleAnswer->value,
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

// ── Ordering ───────────────────────────────────────────────────────

test('guru can create an ordering question', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::Ordering->value,
            'content' => '<p>Urutkan dari yang terkecil</p>',
            'points' => 3,
            'options' => [
                ['label' => '1', 'content' => 'Satu', 'is_correct' => true],
                ['label' => '2', 'content' => 'Dua', 'is_correct' => true],
                ['label' => '3', 'content' => 'Tiga', 'is_correct' => true],
                ['label' => '4', 'content' => 'Empat', 'is_correct' => true],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $question = Question::where('question_bank_id', $this->bank->id)->first();
    expect($question->type)->toBe(QuestionType::Ordering);
    expect($question->options)->toHaveCount(4);
    // Verify order is preserved
    expect($question->options[0]->content)->toBe('Satu');
    expect($question->options[3]->content)->toBe('Empat');
});

test('ordering requires at least 2 items', function () {
    $response = $this->actingAs($this->guru)->post(
        route('guru.bank-soal.soal.store', $this->bank),
        [
            'type' => QuestionType::Ordering->value,
            'content' => '<p>Test</p>',
            'points' => 2,
            'options' => [
                ['label' => '1', 'content' => 'Only one', 'is_correct' => true],
            ],
        ]
    );

    $response->assertSessionHasErrors('options');
});

// ── Delete cleans up related data ──────────────────────────────────

test('deleting isian singkat question also deletes keywords', function () {
    $question = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->bank->id,
    ]);
    $question->keywords()->create(['keyword' => 'test']);
    $question->keywords()->create(['keyword' => 'test2']);

    $this->actingAs($this->guru)->delete(
        route('guru.bank-soal.soal.destroy', [$this->bank, $question])
    );

    $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    $this->assertDatabaseMissing('question_keywords', ['question_id' => $question->id]);
});

test('deleting menjodohkan question also deletes matching pairs', function () {
    $question = Question::factory()->menjodohkan()->create([
        'question_bank_id' => $this->bank->id,
    ]);
    $question->matchingPairs()->create(['premise' => 'P', 'response' => 'R', 'order' => 0]);

    $this->actingAs($this->guru)->delete(
        route('guru.bank-soal.soal.destroy', [$this->bank, $question])
    );

    $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    $this->assertDatabaseMissing('question_matching_pairs', ['question_id' => $question->id]);
});

// ── Edit page loads type-specific data ─────────────────────────────

test('edit page loads keywords for isian singkat', function () {
    $question = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->bank->id,
    ]);
    $question->keywords()->create(['keyword' => 'Jakarta']);
    $question->keywords()->create(['keyword' => 'jakarta']);

    $response = $this->actingAs($this->guru)->get(
        route('guru.bank-soal.soal.edit', [$this->bank, $question])
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Soal/Edit')
        ->has('question.keywords', 2)
    );
});

test('edit page loads matching pairs for menjodohkan', function () {
    $question = Question::factory()->menjodohkan()->create([
        'question_bank_id' => $this->bank->id,
    ]);
    $question->matchingPairs()->create(['premise' => 'Indonesia', 'response' => 'Jakarta', 'order' => 0]);
    $question->matchingPairs()->create(['premise' => 'Malaysia', 'response' => 'KL', 'order' => 1]);

    $response = $this->actingAs($this->guru)->get(
        route('guru.bank-soal.soal.edit', [$this->bank, $question])
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Soal/Edit')
        ->has('question.matching_pairs', 2)
    );
});
