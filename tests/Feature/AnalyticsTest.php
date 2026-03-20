<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\CompetencyStandard;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\ItemAnalysisCache;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\ItemAnalysisService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->admin = User::factory()->admin()->create();
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

    $this->examSession = ExamSession::factory()->completed()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);
    $this->examSession->classrooms()->attach($this->classroom->id);
});

// === Competency Standard CRUD ===

test('guru can view competency management page', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('guru.bank-soal.kompetensi.index', $this->questionBank));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/BankSoal/Kompetensi')
        ->has('questionBank')
        ->has('competencies')
        ->has('questions')
    );
});

test('guru cannot view competency page of another guru bank soal', function () {
    $otherGuru = User::factory()->guru()->create();
    $otherBank = QuestionBank::factory()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.bank-soal.kompetensi.index', $otherBank));

    $response->assertForbidden();
});

test('guru can create competency standard', function () {
    $response = $this->actingAs($this->guru)
        ->post(route('guru.bank-soal.kompetensi.store', $this->questionBank), [
            'code' => 'KD 3.1',
            'name' => 'Memahami konsep jaringan',
            'description' => null,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('competency_standards', [
        'code' => 'KD 3.1',
        'name' => 'Memahami konsep jaringan',
        'subject_id' => $this->subject->id,
    ]);
});

test('guru can update competency standard', function () {
    $kd = CompetencyStandard::create([
        'code' => 'KD 3.1',
        'name' => 'Old Name',
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)
        ->put(route('guru.bank-soal.kompetensi.update', [$this->questionBank, $kd]), [
            'code' => 'KD 3.1',
            'name' => 'New Name',
            'description' => 'Updated description',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('competency_standards', ['id' => $kd->id, 'name' => 'New Name']);
});

test('guru can delete competency standard', function () {
    $kd = CompetencyStandard::create([
        'code' => 'KD 3.1',
        'name' => 'Kompetensi Hapus',
        'subject_id' => $this->subject->id,
    ]);

    $response = $this->actingAs($this->guru)
        ->delete(route('guru.bank-soal.kompetensi.destroy', [$this->questionBank, $kd]));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('competency_standards', ['id' => $kd->id]);
});

test('guru can tag question with competency standards', function () {
    $question = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
    ]);

    $kd1 = CompetencyStandard::create(['code' => 'KD 3.1', 'name' => 'KD 1', 'subject_id' => $this->subject->id]);
    $kd2 = CompetencyStandard::create(['code' => 'KD 3.2', 'name' => 'KD 2', 'subject_id' => $this->subject->id]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.bank-soal.soal.tag-kompetensi', [$this->questionBank, $question]), [
            'competency_standard_ids' => [$kd1->id, $kd2->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('question_competency', ['question_id' => $question->id, 'competency_standard_id' => $kd1->id]);
    $this->assertDatabaseHas('question_competency', ['question_id' => $question->id, 'competency_standard_id' => $kd2->id]);
});

test('guru can remove all competency tags from question', function () {
    $question = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
    ]);

    $kd = CompetencyStandard::create(['code' => 'KD 3.1', 'name' => 'KD 1', 'subject_id' => $this->subject->id]);
    $question->competencyStandards()->attach($kd->id);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.bank-soal.soal.tag-kompetensi', [$this->questionBank, $question]), [
            'competency_standard_ids' => [],
        ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('question_competency', ['question_id' => $question->id]);
});

// === Item Analysis ===

test('guru can view item analysis page', function () {
    $cache = ItemAnalysisCache::create([
        'exam_session_id' => $this->examSession->id,
        'analysis_data' => [
            'exam_session_id' => $this->examSession->id,
            'computed_at' => now()->toISOString(),
            'items' => [],
            'summary' => [
                'total_questions' => 0,
                'easy_count' => 0,
                'medium_count' => 0,
                'hard_count' => 0,
                'good_discrimination_count' => 0,
                'fair_discrimination_count' => 0,
                'poor_discrimination_count' => 0,
            ],
            'kd_breakdown' => [],
        ],
        'computed_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.item-analysis', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Penilaian/ItemAnalysis')
        ->has('examSession')
        ->has('analysis')
        ->has('attemptCount')
    );
});

test('guru cannot view item analysis of another guru exam session', function () {
    $otherGuru = User::factory()->guru()->create();
    $otherSession = ExamSession::factory()->completed()->create([
        'user_id' => $otherGuru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.item-analysis', $otherSession));

    $response->assertForbidden();
});

test('item analysis returns computing state when no cache exists', function () {
    Queue::fake();

    $response = $this->actingAs($this->guru)
        ->get(route('guru.grading.item-analysis', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('analysis.computing', true)
        ->where('analysis.items', [])
    );
});

test('guru can trigger item analysis refresh', function () {
    Queue::fake();

    $response = $this->actingAs($this->guru)
        ->post(route('guru.grading.item-analysis.refresh', $this->examSession));

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

// === ItemAnalysisService Unit Tests ===

test('item analysis service returns empty result when no attempts', function () {
    $service = app(ItemAnalysisService::class);
    $result = $service->analyzeExamSession($this->examSession);

    expect($result['items'])->toBeEmpty()
        ->and($result['summary']['total_questions'])->toBe(0);
});

test('item analysis service computes difficulty index correctly', function () {
    $question = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'order' => 1,
    ]);

    QuestionOption::factory()->create([
        'question_id' => $question->id,
        'label' => 'A',
        'is_correct' => true,
        'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $question->id,
        'label' => 'B',
        'is_correct' => false,
        'order' => 1,
    ]);

    // 4 siswa: 3 correct + 1 wrong → p = 0.75 (mudah)
    for ($i = 0; $i < 4; $i++) {
        $siswa = User::factory()->siswa()->create();
        $attempt = ExamAttempt::create([
            'exam_session_id' => $this->examSession->id,
            'user_id' => $siswa->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'ip_address' => '127.0.0.1',
            'status' => ExamAttemptStatus::Submitted,
            'score' => $i < 3 ? 100 : 0,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer' => $i < 3 ? 'A' : 'B',
            'is_correct' => $i < 3,
            'score' => $i < 3 ? 1.0 : 0.0,
            'answered_at' => now(),
        ]);
    }

    $service = app(ItemAnalysisService::class);
    $result = $service->analyzeExamSession($this->examSession);

    expect($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['difficulty_index'])->toBe(0.75)
        ->and($result['items'][0]['difficulty_label'])->toBe('mudah');
});

test('item analysis service builds kd breakdown correctly', function () {
    $kd = CompetencyStandard::create([
        'code' => 'KD 3.1',
        'name' => 'Konsep Jaringan',
        'subject_id' => $this->subject->id,
    ]);

    $question = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'order' => 1,
    ]);
    $question->competencyStandards()->attach($kd->id);

    QuestionOption::factory()->create([
        'question_id' => $question->id,
        'label' => 'A',
        'is_correct' => true,
        'order' => 0,
    ]);

    // 2 attempts all correct → difficulty = 1.0 (mudah), avg_score = 100
    for ($i = 0; $i < 2; $i++) {
        $siswa = User::factory()->siswa()->create();
        $attempt = ExamAttempt::create([
            'exam_session_id' => $this->examSession->id,
            'user_id' => $siswa->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'ip_address' => '127.0.0.1',
            'status' => ExamAttemptStatus::Submitted,
            'score' => 100,
        ]);
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer' => 'A',
            'is_correct' => true,
            'score' => 1.0,
            'answered_at' => now(),
        ]);
    }

    $service = app(ItemAnalysisService::class);
    $result = $service->analyzeExamSession($this->examSession);

    expect($result['kd_breakdown'])->toHaveCount(1)
        ->and($result['kd_breakdown'][0]['code'])->toBe('KD 3.1')
        ->and($result['kd_breakdown'][0]['question_count'])->toBe(1)
        ->and($result['kd_breakdown'][0]['avg_score'])->toBe(100.0);
});

// === Admin Analytics ===

test('admin can view analytics index page', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.analytics.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Analytics/Index')
        ->has('academicYears')
        ->has('departments')
        ->has('classroomStats')
        ->has('classroomComparison')
        ->has('filters')
    );
});

test('admin can view classroom detail analytics', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.analytics.classroom', $this->classroom));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Analytics/ClassroomDetail')
        ->has('classroom')
        ->has('trend')
    );
});

test('guru cannot access admin analytics', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('admin.analytics.index'));

    $response->assertForbidden();
});
