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
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\Exam\ExamAttemptService;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\ExamTestHelper;

uses(ExamTestHelper::class);

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

/**
 * Helper: create attempt + questions + answers for a given set of questions.
 */
function createAttemptWithAnswers(
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

function submitAttempt(ExamAttempt $attempt): void
{
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->times(3)->andReturn(1);

    app(ExamAttemptService::class)->submitExam($attempt);
}

// ===== Full score =====

test('all correct PG answers give 100% score', function () {
    $questions = $this->createPgQuestions($this->questionBank, 5, 2);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = createAttemptWithAnswers($session, $this->siswa, $questions, array_fill(0, 5, 'A'));
    submitAttempt($attempt);
    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);

    foreach ($attempt->answers as $answer) {
        expect($answer->is_correct)->toBeTrue();
        expect((float) $answer->score)->toBe(2.0);
    }
});

// ===== Zero score =====

test('all wrong PG answers give 0% score', function () {
    $questions = $this->createPgQuestions($this->questionBank, 5, 2);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = createAttemptWithAnswers($session, $this->siswa, $questions, array_fill(0, 5, 'B'));
    submitAttempt($attempt);
    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(0.0);

    foreach ($attempt->answers as $answer) {
        expect($answer->is_correct)->toBeFalse();
        expect((float) $answer->score)->toBe(0.0);
    }
});

// ===== Partial score =====

test('mix of correct and wrong gives proportional score', function () {
    $questions = $this->createPgQuestions($this->questionBank, 4, 2);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    // 3 correct (A), 1 wrong (B) → 75%
    $attempt = createAttemptWithAnswers($session, $this->siswa, $questions, ['A', 'A', 'A', 'B']);
    submitAttempt($attempt);
    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(75.0);
});

// ===== Mixed PG + B/S + Esai =====

test('mixed question types: PG and BS are auto-graded, esai is not', function () {
    $questions = $this->createStandardQuestionSet($this->questionBank);
    $allQuestions = array_merge($questions['pg'], $questions['bs'], $questions['esai']);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    // All PG correct (A), all BS correct (A), esai has text
    $answers = array_merge(
        array_fill(0, 3, 'A'), // 3 PG correct
        array_fill(0, 2, 'A'), // 2 BS correct
        ['Jawaban esai yang panjang dan detail.'],
    );

    $attempt = createAttemptWithAnswers($session, $this->siswa, $allQuestions, $answers);
    submitAttempt($attempt);
    $attempt->refresh();

    // NOT fully graded (esai not yet graded)
    expect($attempt->is_fully_graded)->toBeFalse();

    // PG all correct
    foreach ($questions['pg'] as $q) {
        $ans = $attempt->answers->firstWhere('question_id', $q->id);
        expect($ans->is_correct)->toBeTrue();
        expect((float) $ans->score)->toBe(2.0);
    }

    // BS all correct
    foreach ($questions['bs'] as $q) {
        $ans = $attempt->answers->firstWhere('question_id', $q->id);
        expect($ans->is_correct)->toBeTrue();
        expect((float) $ans->score)->toBe(2.0);
    }

    // Esai NOT graded
    $esaiAns = $attempt->answers->firstWhere('question_id', $questions['esai'][0]->id);
    expect($esaiAns->is_correct)->toBeNull();
    expect($esaiAns->score)->toBeNull();
});

// ===== Different point weights =====

test('questions with different point weights calculate score correctly', function () {
    // Create questions with different points: 2, 4, 6, 8
    $questions = [];
    foreach ([2, 4, 6, 8] as $i => $pts) {
        $q = Question::factory()->pilihanGanda()->create([
            'question_bank_id' => $this->questionBank->id,
            'points' => $pts,
            'order' => $i + 1,
        ]);
        QuestionOption::factory()->correct()->create([
            'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
        ]);
        $questions[] = $q;
    }

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    // First two correct (2+4=6 points), last two wrong → 6/20 = 30%
    $attempt = createAttemptWithAnswers($session, $this->siswa, $questions, ['A', 'A', 'B', 'B']);
    submitAttempt($attempt);
    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeTrue();
    expect((float) $attempt->score)->toBe(30.0);

    // Verify individual scores
    $answers = $attempt->answers->keyBy('question_id');
    expect((float) $answers[$questions[0]->id]->score)->toBe(2.0);
    expect((float) $answers[$questions[1]->id]->score)->toBe(4.0);
    expect((float) $answers[$questions[2]->id]->score)->toBe(0.0);
    expect((float) $answers[$questions[3]->id]->score)->toBe(0.0);
});

test('high weight question correct and low weight wrong gives weighted score', function () {
    // 1 question worth 10, 1 question worth 2
    $q10 = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 10, 'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $q10->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $q10->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $q2 = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 2, 'order' => 2,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $q2->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $q2->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    // 10-pt correct, 2-pt wrong → 10/12 = 83.33%
    $attempt = createAttemptWithAnswers($session, $this->siswa, [$q10, $q2], ['A', 'B']);
    submitAttempt($attempt);
    $attempt->refresh();

    expect((float) $attempt->score)->toBe(83.33);
});

// ===== Unanswered =====

test('unanswered PG questions get zero score', function () {
    $questions = $this->createPgQuestions($this->questionBank, 3, 2);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    // 1 correct, 2 unanswered
    $attempt = createAttemptWithAnswers($session, $this->siswa, $questions, ['A', null, null]);
    submitAttempt($attempt);
    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeTrue();
    // 2/6 = 33.33%
    expect((float) $attempt->score)->toBe(33.33);
});

// ===== B/S grading edge cases =====

test('benar-salah with correct=Salah (B) is graded correctly', function () {
    // Create B/S where correct answer is B (Salah)
    $q = Question::factory()->benarSalah()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 2, 'order' => 1,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
    ]);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    // Student answers B (correct in this case)
    $attempt = createAttemptWithAnswers($session, $this->siswa, [$q], ['B']);
    submitAttempt($attempt);
    $attempt->refresh();

    $answer = $attempt->answers->first();
    expect($answer->is_correct)->toBeTrue();
    expect((float) $answer->score)->toBe(2.0);
    expect((float) $attempt->score)->toBe(100.0);
});

// ===== Only esai =====

test('exam with only esai questions is not fully graded', function () {
    $esai1 = Question::factory()->esai()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 10, 'order' => 1,
    ]);
    $esai2 = Question::factory()->esai()->create([
        'question_bank_id' => $this->questionBank->id, 'points' => 10, 'order' => 2,
    ]);

    $session = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'is_randomize_questions' => false,
        'is_randomize_options' => false,
    ]);
    $session->classrooms()->attach($this->classroom->id);

    $attempt = createAttemptWithAnswers($session, $this->siswa, [$esai1, $esai2], [
        'Jawaban esai 1.', 'Jawaban esai 2.',
    ]);
    submitAttempt($attempt);
    $attempt->refresh();

    expect($attempt->is_fully_graded)->toBeFalse();

    // Both answers should not have scores
    foreach ($attempt->answers as $answer) {
        expect($answer->is_correct)->toBeNull();
        expect($answer->score)->toBeNull();
    }
});
