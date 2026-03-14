<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Enums\QuestionType;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;

/**
 * Reusable helper functions for exam-related tests.
 * Used via `uses(ExamTestHelper::class)` in Pest.
 */
trait ExamTestHelper
{
    /**
     * Bootstrap a full exam environment: guru, siswa, classroom, bank, questions, session.
     *
     * @return array{guru: User, siswa: User, academicYear: AcademicYear, department: Department, subject: Subject, classroom: Classroom, questionBank: QuestionBank, questions: array<Question>, examSession: ExamSession}
     */
    protected function createExamEnvironment(array $overrides = []): array
    {
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa()->create();

        $academicYear = AcademicYear::factory()->active()->create();
        $department = Department::factory()->create();
        $subject = Subject::factory()->create(['department_id' => $department->id]);

        $classroom = Classroom::factory()->create([
            'academic_year_id' => $academicYear->id,
            'department_id' => $department->id,
        ]);
        $classroom->students()->attach($siswa->id);

        $questionBank = QuestionBank::factory()->create([
            'user_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);

        $questions = $this->createStandardQuestionSet($questionBank);

        $examSession = ExamSession::factory()->active()->create(array_merge([
            'user_id' => $guru->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $academicYear->id,
            'question_bank_id' => $questionBank->id,
            'token' => 'ABCDEF',
            'is_randomize_questions' => false,
            'is_randomize_options' => false,
        ], $overrides));

        $examSession->classrooms()->attach($classroom->id);

        return compact(
            'guru', 'siswa', 'academicYear', 'department', 'subject',
            'classroom', 'questionBank', 'questions', 'examSession',
        );
    }

    /**
     * Create a standard question set: 3 PG + 2 B/S + 1 Esai.
     *
     * @return array{pg: array<Question>, bs: array<Question>, esai: array<Question>}
     */
    protected function createStandardQuestionSet(QuestionBank $bank): array
    {
        $pg = [];
        for ($i = 1; $i <= 3; $i++) {
            $q = Question::factory()->pilihanGanda()->create([
                'question_bank_id' => $bank->id,
                'points' => 2,
                'order' => $i,
            ]);
            QuestionOption::factory()->correct()->create([
                'question_id' => $q->id, 'label' => 'A', 'content' => "Jawaban benar PG {$i}", 'order' => 0,
            ]);
            QuestionOption::factory()->create([
                'question_id' => $q->id, 'label' => 'B', 'content' => "Salah B {$i}", 'order' => 1,
            ]);
            QuestionOption::factory()->create([
                'question_id' => $q->id, 'label' => 'C', 'content' => "Salah C {$i}", 'order' => 2,
            ]);
            QuestionOption::factory()->create([
                'question_id' => $q->id, 'label' => 'D', 'content' => "Salah D {$i}", 'order' => 3,
            ]);
            $pg[] = $q;
        }

        $bs = [];
        for ($i = 1; $i <= 2; $i++) {
            $q = Question::factory()->benarSalah()->create([
                'question_bank_id' => $bank->id,
                'points' => 2,
                'order' => 3 + $i,
            ]);
            QuestionOption::factory()->correct()->create([
                'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
            ]);
            QuestionOption::factory()->create([
                'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah', 'order' => 1,
            ]);
            $bs[] = $q;
        }

        $esai = [];
        $esai[] = Question::factory()->esai()->create([
            'question_bank_id' => $bank->id,
            'points' => 10,
            'order' => 6,
        ]);

        return compact('pg', 'bs', 'esai');
    }

    /**
     * Create only PG questions (for auto-grade-only tests).
     *
     * @return array<Question>
     */
    protected function createPgQuestions(QuestionBank $bank, int $count = 5, float $points = 2): array
    {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $q = Question::factory()->pilihanGanda()->create([
                'question_bank_id' => $bank->id,
                'points' => $points,
                'order' => $i,
            ]);
            QuestionOption::factory()->correct()->create([
                'question_id' => $q->id, 'label' => 'A', 'content' => 'Benar', 'order' => 0,
            ]);
            QuestionOption::factory()->create([
                'question_id' => $q->id, 'label' => 'B', 'content' => 'Salah 1', 'order' => 1,
            ]);
            QuestionOption::factory()->create([
                'question_id' => $q->id, 'label' => 'C', 'content' => 'Salah 2', 'order' => 2,
            ]);
            QuestionOption::factory()->create([
                'question_id' => $q->id, 'label' => 'D', 'content' => 'Salah 3', 'order' => 3,
            ]);
            $questions[] = $q;
        }

        return $questions;
    }
}
