<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionKeyword;
use App\Models\QuestionMatchingPair;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\Exam\ExamAttemptService;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);
    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
    $this->classroom->students()->attach($this->siswa->id);
    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);
});

function createSession($test): ExamSession
{
    $session = ExamSession::factory()->active()->create([
        'user_id' => $test->guru->id,
        'subject_id' => $test->subject->id,
        'academic_year_id' => $test->academicYear->id,
        'question_bank_id' => $test->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($test->classroom->id);

    return $session;
}

function createAttemptForQuestions(
    ExamSession $session,
    User $student,
    array $questions,
    array $answers,
): ExamAttempt {
    $attempt = ExamAttempt::create([
        'exam_session_id' => $session->id,
        'user_id' => $student->id,
        'started_at' => now(),
        'ip_address' => '127.0.0.1',
        'status' => ExamAttemptStatus::InProgress,
    ]);

    foreach ($questions as $i => $question) {
        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'order' => $i + 1,
        ]);

        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer' => $answers[$i] ?? null,
            'answered_at' => isset($answers[$i]) ? now() : null,
        ]);
    }

    return $attempt;
}

function submitAndRefresh(ExamAttempt $attempt): void
{
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    app(ExamAttemptService::class)->submitExam($attempt);
    $attempt->refresh();
}

// ===== Isian Singkat Auto-Grading =====

test('isian singkat: exact keyword match gives full score', function () {
    $q = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    $q->keywords()->createMany([
        ['keyword' => 'Jakarta'],
        ['keyword' => 'DKI Jakarta'],
    ]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], ['Jakarta']);
    submitAndRefresh($attempt);

    $answer = $attempt->answers->first();
    expect($answer->is_correct)->toBeTrue();
    expect((float) $answer->score)->toBe(5.0);
    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);
});

test('isian singkat: case-insensitive matching', function () {
    $q = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    $q->keywords()->create(['keyword' => 'Jakarta']);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], ['jakarta']);
    submitAndRefresh($attempt);

    $answer = $attempt->answers->first();
    expect($answer->is_correct)->toBeTrue();
    expect((float) $answer->score)->toBe(5.0);
});

test('isian singkat: alternative keyword match gives full score', function () {
    $q = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    $q->keywords()->createMany([
        ['keyword' => 'Jakarta'],
        ['keyword' => 'DKI Jakarta'],
    ]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], ['DKI Jakarta']);
    submitAndRefresh($attempt);

    $answer = $attempt->answers->first();
    expect($answer->is_correct)->toBeTrue();
    expect((float) $answer->score)->toBe(5.0);
});

test('isian singkat: wrong answer gives zero', function () {
    $q = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    $q->keywords()->create(['keyword' => 'Jakarta']);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], ['Bandung']);
    submitAndRefresh($attempt);

    $answer = $attempt->answers->first();
    expect($answer->is_correct)->toBeFalse();
    expect((float) $answer->score)->toBe(0.0);
});

test('isian singkat: empty answer gives zero', function () {
    $q = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    $q->keywords()->create(['keyword' => 'Jakarta']);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [null]);
    submitAndRefresh($attempt);

    $answer = $attempt->answers->first();
    expect($answer->is_correct)->toBeFalse();
    expect((float) $answer->score)->toBe(0.0);
});

// ===== Menjodohkan Auto-Grading =====

test('menjodohkan: all correct matches give full score', function () {
    $q = Question::factory()->menjodohkan()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 6,
        'order' => 1,
    ]);
    $pair1 = $q->matchingPairs()->create(['premise' => 'Indonesia', 'response' => 'Jakarta', 'order' => 0]);
    $pair2 = $q->matchingPairs()->create(['premise' => 'Malaysia', 'response' => 'KL', 'order' => 1]);
    $pair3 = $q->matchingPairs()->create(['premise' => 'Thailand', 'response' => 'Bangkok', 'order' => 2]);

    // Student maps each premise to its own pair (correct)
    $answer = json_encode([
        (string) $pair1->id => (string) $pair1->id,
        (string) $pair2->id => (string) $pair2->id,
        (string) $pair3->id => (string) $pair3->id,
    ]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeTrue();
    expect((float) $ans->score)->toBe(6.0);
    expect((float) $attempt->score)->toBe(100.0);
});

test('menjodohkan: partial matches give proportional score', function () {
    $q = Question::factory()->menjodohkan()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 6,
        'order' => 1,
    ]);
    $pair1 = $q->matchingPairs()->create(['premise' => 'Indonesia', 'response' => 'Jakarta', 'order' => 0]);
    $pair2 = $q->matchingPairs()->create(['premise' => 'Malaysia', 'response' => 'KL', 'order' => 1]);
    $pair3 = $q->matchingPairs()->create(['premise' => 'Thailand', 'response' => 'Bangkok', 'order' => 2]);

    // 2 out of 3 correct
    $answer = json_encode([
        (string) $pair1->id => (string) $pair1->id,
        (string) $pair2->id => (string) $pair2->id,
        (string) $pair3->id => (string) $pair1->id, // wrong
    ]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(4.0); // 2/3 * 6 = 4
});

test('menjodohkan: all wrong gives zero', function () {
    $q = Question::factory()->menjodohkan()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 6,
        'order' => 1,
    ]);
    $pair1 = $q->matchingPairs()->create(['premise' => 'A', 'response' => 'R1', 'order' => 0]);
    $pair2 = $q->matchingPairs()->create(['premise' => 'B', 'response' => 'R2', 'order' => 1]);

    // All wrong (swapped)
    $answer = json_encode([
        (string) $pair1->id => (string) $pair2->id,
        (string) $pair2->id => (string) $pair1->id,
    ]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(0.0);
});

test('menjodohkan: no answer gives zero', function () {
    $q = Question::factory()->menjodohkan()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 6,
        'order' => 1,
    ]);
    $q->matchingPairs()->create(['premise' => 'A', 'response' => 'R1', 'order' => 0]);
    $q->matchingPairs()->create(['premise' => 'B', 'response' => 'R2', 'order' => 1]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [null]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(0.0);
});

// ===== Multiple Answer Auto-Grading =====

test('multiple answer: all correct selected gives full score', function () {
    $q = Question::factory()->multipleAnswer()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 4,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $q->id, 'label' => 'A', 'content' => '2', 'order' => 0]);
    QuestionOption::factory()->create(['question_id' => $q->id, 'label' => 'B', 'content' => '4', 'order' => 1]);
    QuestionOption::factory()->correct()->create(['question_id' => $q->id, 'label' => 'C', 'content' => '5', 'order' => 2]);
    QuestionOption::factory()->create(['question_id' => $q->id, 'label' => 'D', 'content' => '9', 'order' => 3]);

    $answer = json_encode(['A', 'C']); // correct: A and C

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeTrue();
    expect((float) $ans->score)->toBe(4.0);
    expect((float) $attempt->score)->toBe(100.0);
});

test('multiple answer: missing one correct gives zero', function () {
    $q = Question::factory()->multipleAnswer()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 4,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $q->id, 'label' => 'A', 'content' => '2', 'order' => 0]);
    QuestionOption::factory()->create(['question_id' => $q->id, 'label' => 'B', 'content' => '4', 'order' => 1]);
    QuestionOption::factory()->correct()->create(['question_id' => $q->id, 'label' => 'C', 'content' => '5', 'order' => 2]);

    $answer = json_encode(['A']); // missing C

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(0.0);
});

test('multiple answer: selecting incorrect option gives zero', function () {
    $q = Question::factory()->multipleAnswer()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 4,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $q->id, 'label' => 'A', 'content' => '2', 'order' => 0]);
    QuestionOption::factory()->create(['question_id' => $q->id, 'label' => 'B', 'content' => '4', 'order' => 1]);
    QuestionOption::factory()->correct()->create(['question_id' => $q->id, 'label' => 'C', 'content' => '5', 'order' => 2]);

    $answer = json_encode(['A', 'B', 'C']); // includes B which is wrong

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(0.0);
});

test('multiple answer: no answer gives zero', function () {
    $q = Question::factory()->multipleAnswer()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 4,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $q->id, 'label' => 'A', 'content' => '2', 'order' => 0]);
    QuestionOption::factory()->create(['question_id' => $q->id, 'label' => 'B', 'content' => '4', 'order' => 1]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [null]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(0.0);
});

// ===== Ordering Auto-Grading =====

test('ordering: correct order gives full score', function () {
    $q = Question::factory()->ordering()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    $opt1 = QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '1', 'content' => 'Pertama', 'order' => 0, 'is_correct' => true]);
    $opt2 = QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '2', 'content' => 'Kedua', 'order' => 1, 'is_correct' => true]);
    $opt3 = QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '3', 'content' => 'Ketiga', 'order' => 2, 'is_correct' => true]);

    $answer = json_encode([$opt1->id, $opt2->id, $opt3->id]); // correct order

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeTrue();
    expect((float) $ans->score)->toBe(5.0);
    expect((float) $attempt->score)->toBe(100.0);
});

test('ordering: wrong order gives zero', function () {
    $q = Question::factory()->ordering()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    $opt1 = QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '1', 'content' => 'Pertama', 'order' => 0, 'is_correct' => true]);
    $opt2 = QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '2', 'content' => 'Kedua', 'order' => 1, 'is_correct' => true]);
    $opt3 = QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '3', 'content' => 'Ketiga', 'order' => 2, 'is_correct' => true]);

    $answer = json_encode([$opt3->id, $opt1->id, $opt2->id]); // wrong order

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [$answer]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(0.0);
});

test('ordering: no answer gives zero', function () {
    $q = Question::factory()->ordering()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 5,
        'order' => 1,
    ]);
    QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '1', 'content' => 'Pertama', 'order' => 0, 'is_correct' => true]);
    QuestionOption::factory()->create(['question_id' => $q->id, 'label' => '2', 'content' => 'Kedua', 'order' => 1, 'is_correct' => true]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$q], [null]);
    submitAndRefresh($attempt);

    $ans = $attempt->answers->first();
    expect($ans->is_correct)->toBeFalse();
    expect((float) $ans->score)->toBe(0.0);
});

// ===== Mixed new types =====

test('mixed new types: all auto-graded correctly', function () {
    // Isian Singkat
    $qIsi = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 2, 'order' => 1,
    ]);
    $qIsi->keywords()->create(['keyword' => 'Jakarta']);

    // Multiple Answer
    $qMa = Question::factory()->multipleAnswer()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 3, 'order' => 2,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $qMa->id, 'label' => 'A', 'content' => '2', 'order' => 0]);
    QuestionOption::factory()->create(['question_id' => $qMa->id, 'label' => 'B', 'content' => '4', 'order' => 1]);
    QuestionOption::factory()->correct()->create(['question_id' => $qMa->id, 'label' => 'C', 'content' => '5', 'order' => 2]);

    // Ordering
    $qOrd = Question::factory()->ordering()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 5, 'order' => 3,
    ]);
    $o1 = QuestionOption::factory()->create(['question_id' => $qOrd->id, 'label' => '1', 'content' => 'First', 'order' => 0, 'is_correct' => true]);
    $o2 = QuestionOption::factory()->create(['question_id' => $qOrd->id, 'label' => '2', 'content' => 'Second', 'order' => 1, 'is_correct' => true]);

    $questions = [$qIsi, $qMa, $qOrd];
    $answers = [
        'Jakarta',                              // isian singkat: correct
        json_encode(['A', 'C']),                 // multiple answer: correct
        json_encode([$o1->id, $o2->id]),         // ordering: correct
    ];

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, $questions, $answers);
    submitAndRefresh($attempt);

    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);
});

test('mixed with esai: not fully graded when esai present', function () {
    // Isian Singkat (auto-graded)
    $qIsi = Question::factory()->isianSingkat()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 5, 'order' => 1,
    ]);
    $qIsi->keywords()->create(['keyword' => 'Test']);

    // Esai (manual)
    $qEsai = Question::factory()->esai()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 10, 'order' => 2,
    ]);

    $session = createSession($this);
    $attempt = createAttemptForQuestions($session, $this->siswa, [$qIsi, $qEsai], [
        'Test', 'Jawaban esai panjang.',
    ]);
    submitAndRefresh($attempt);

    expect($attempt->is_fully_graded)->toBeFalse();

    // Isian singkat should be graded
    $isiAns = $attempt->answers->firstWhere('question_id', $qIsi->id);
    expect($isiAns->is_correct)->toBeTrue();
    expect((float) $isiAns->score)->toBe(5.0);

    // Esai should not be graded
    $esaiAns = $attempt->answers->firstWhere('question_id', $qEsai->id);
    expect($esaiAns->is_correct)->toBeNull();
    expect($esaiAns->score)->toBeNull();
});
