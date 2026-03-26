<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\ExamSessionRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\UjianDijadwalkanNotification;
use App\Services\Exam\ExamSessionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ExamSessionService $sessionService,
    ) {}

    public function index(Request $request): Response
    {
        $query = ExamSession::where('user_id', $request->user()->id)
            ->with(['subject', 'classrooms', 'questionBank'])
            ->withCount('attempts');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $examSessions = $query->latest()->paginate(15)->withQueryString();

        // Sync statuses
        foreach ($examSessions as $session) {
            $this->sessionService->syncStatus($session);
        }

        return Inertia::render('Guru/Ujian/Index', [
            'examSessions' => $examSessions,
            'filters' => $request->only(['search', 'status']),
            'statuses' => collect(ExamStatus::cases())->map(fn (ExamStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Guru/Ujian/Create', [
            'subjects' => $this->getSubjectsForGuru($request->user()),
            'questionBanks' => $this->getQuestionBanksForGuru($request->user()),
            'academicYears' => AcademicYear::select('id', 'name', 'semester', 'is_active')
                ->orderByDesc('is_active')
                ->orderByDesc('starts_at')
                ->get(),
            'classrooms' => Classroom::with('department')
                ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get()
                ->map(fn (Classroom $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'department' => $c->department?->name,
                ]),
        ]);
    }

    public function store(ExamSessionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $examSession = ExamSession::create([
            ...Arr::except($data, ['classroom_ids']),
            'user_id' => $request->user()->id,
            'token' => $this->sessionService->generateToken(),
            'status' => ExamStatus::Scheduled,
        ]);

        $examSession->classrooms()->sync($data['classroom_ids']);

        if ($examSession->is_published) {
            $this->dispatchUjianNotification($examSession);
        }

        return redirect()->route('guru.ujian.show', $examSession)
            ->with('success', 'Sesi ujian berhasil dibuat.');
    }

    public function show(ExamSession $ujian): Response
    {
        $this->authorize('view', $ujian);
        $this->sessionService->syncStatus($ujian);

        // F2.1: Load only question metadata (no content/options) and count attempts separately
        $ujian->load([
            'subject',
            'questionBank.questions:id,question_bank_id',
            'classrooms.department',
            'academicYear',
        ]);
        $ujian->loadCount('attempts');

        // Load attempts limited to 100 most recent with minimal user fields
        $attempts = $ujian->attempts()
            ->with('user:id,name,username')
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Guru/Ujian/Show', [
            'examSession' => $ujian,
            'attempts' => $attempts,
        ]);
    }

    public function edit(Request $request, ExamSession $ujian): Response
    {
        $this->authorize('update', $ujian);

        $ujian->load('classrooms');

        return Inertia::render('Guru/Ujian/Edit', [
            'examSession' => $ujian,
            'subjects' => $this->getSubjectsForGuru($request->user()),
            'questionBanks' => $this->getQuestionBanksForGuru($request->user()),
            'academicYears' => AcademicYear::select('id', 'name', 'semester', 'is_active')
                ->orderByDesc('is_active')
                ->orderByDesc('starts_at')
                ->get(),
            'classrooms' => Classroom::with('department')
                ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get()
                ->map(fn (Classroom $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'department' => $c->department?->name,
                ]),
        ]);
    }

    public function update(ExamSessionRequest $request, ExamSession $ujian): RedirectResponse
    {
        $this->authorize('update', $ujian);

        $data = $request->validated();

        $ujian->update(Arr::except($data, ['classroom_ids']));
        $ujian->classrooms()->sync($data['classroom_ids']);

        if ($ujian->wasChanged('is_published') && $ujian->is_published) {
            $this->dispatchUjianNotification($ujian);
        }

        return redirect()->route('guru.ujian.show', $ujian)
            ->with('success', 'Sesi ujian berhasil diperbarui.');
    }

    public function destroy(ExamSession $ujian): RedirectResponse
    {
        $this->authorize('delete', $ujian);

        $ujian->delete();

        return redirect()->route('guru.ujian.index')
            ->with('success', 'Sesi ujian berhasil dihapus.');
    }

    /**
     * Show create remedial exam form, pre-filled from original exam.
     */
    public function createRemedial(Request $request, ExamSession $ujian): Response
    {
        $this->authorize('view', $ujian);

        $ujian->load(['subject', 'classrooms', 'questionBank']);

        // Get students who need remedial (score < KKM)
        $kkm = (float) ($ujian->kkm ?? 0);
        $remedialStudents = [];

        if ($kkm > 0) {
            $remedialStudents = $ujian->attempts()
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
                ->where(function ($q) use ($kkm) {
                    $q->where('score', '<', $kkm)->orWhereNull('score');
                })
                ->with('user:id,name,username')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->user->id,
                    'name' => $a->user->name,
                    'username' => $a->user->username,
                    'score' => $a->score !== null ? (float) $a->score : null,
                ])
                ->toArray();
        }

        return Inertia::render('Guru/Ujian/CreateRemedial', [
            'originalExam' => [
                'id' => $ujian->id,
                'name' => $ujian->name,
                'subject' => $ujian->subject->name,
                'subject_id' => $ujian->subject_id,
                'question_bank_id' => $ujian->question_bank_id,
                'question_bank_name' => $ujian->questionBank?->name,
                'duration_minutes' => $ujian->duration_minutes,
                'kkm' => $kkm,
                'classroom_ids' => $ujian->classrooms->pluck('id')->toArray(),
            ],
            'remedialStudents' => $remedialStudents,
            'subjects' => $this->getSubjectsForGuru($request->user()),
            'questionBanks' => $this->getQuestionBanksForGuru($request->user()),
            'academicYears' => AcademicYear::select('id', 'name', 'semester', 'is_active')
                ->orderByDesc('is_active')
                ->orderByDesc('starts_at')
                ->get(),
            'classrooms' => Classroom::with('department')
                ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get()
                ->map(fn (Classroom $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'department' => $c->department?->name,
                ]),
        ]);
    }

    /**
     * Store a new remedial exam session.
     */
    public function storeRemedial(ExamSessionRequest $request, ExamSession $ujian): RedirectResponse
    {
        $this->authorize('view', $ujian);

        $request->validate([
            'remedial_policy' => ['required', 'string', 'in:highest,capped_at_kkm'],
        ]);

        $data = $request->validated();

        $examSession = ExamSession::create([
            ...Arr::except($data, ['classroom_ids', 'remedial_policy']),
            'user_id' => $request->user()->id,
            'token' => $this->sessionService->generateToken(),
            'status' => ExamStatus::Scheduled,
            'original_exam_session_id' => $ujian->id,
            'remedial_policy' => $request->input('remedial_policy'),
        ]);

        $examSession->classrooms()->sync($data['classroom_ids']);

        return redirect()->route('guru.ujian.show', $examSession)
            ->with('success', 'Ujian remedial berhasil dibuat.');
    }

    /**
     * Update status ujian (draft → scheduled, activate, complete).
     */
    public function updateStatus(Request $request, ExamSession $ujian): RedirectResponse
    {
        $this->authorize('update', $ujian);

        $request->validate([
            'status' => ['required', 'string'],
        ]);

        $newStatus = ExamStatus::from($request->string('status')->value());

        $ujian->update(['status' => $newStatus]);

        return back()->with('success', 'Status ujian diperbarui.');
    }

    /**
     * Generate printable PDF of exam questions.
     */
    public function printPdf(ExamSession $ujian): \Illuminate\Http\Response
    {
        $this->authorize('view', $ujian);

        $ujian->load(['subject', 'questionBank', 'classrooms']);

        $questions = $ujian->questions()
            ->with(['options', 'matchingPairs', 'keywords'])
            ->get();

        if ($questions->isEmpty() && $ujian->questionBank) {
            $questions = $ujian->questionBank->questions()
                ->with(['options', 'matchingPairs', 'keywords'])
                ->get();
        }

        $pdf = Pdf::loadView('pdf.exam-questions', [
            'examSession' => $ujian,
            'questions' => $questions,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'soal-'.str_replace(' ', '-', strtolower($ujian->name)).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate PDF of participant cards for printing.
     */
    public function printParticipantCards(ExamSession $ujian): \Illuminate\Http\Response
    {
        $this->authorize('view', $ujian);

        $ujian->load(['subject', 'classrooms.students', 'classrooms.department']);

        if ($ujian->classrooms->isEmpty()) {
            abort(422, 'Belum ada kelas yang ditugaskan untuk ujian ini.');
        }

        $students = $ujian->classrooms->flatMap(function ($classroom) {
            return $classroom->students->map(function ($student) use ($classroom) {
                $nameParts = explode(' ', trim($student->name));
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

                return [
                    'name' => $student->name,
                    'nis' => $student->username,
                    'classroom' => $classroom->name,
                    'department' => $classroom->department?->name,
                    'photo_path' => $student->photo_path ?? null,
                    'initials' => $initials,
                ];
            });
        })->sortBy('name')->values();

        $logoPath = setting('logo_path');
        $schoolName = setting('school_name', config('app.name', 'SMK'));
        $examDate = Carbon::parse($ujian->starts_at)->translatedFormat('d F Y');

        $pdf = Pdf::loadView('pdf.participant-cards', [
            'examSession' => $ujian,
            'students' => $students,
            'logoPath' => $logoPath,
            'schoolName' => $schoolName,
            'examDate' => $examDate,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'kartu-peserta-' . str_replace(' ', '-', strtolower($ujian->name)) . '.pdf';

        return $pdf->download($filename);
    }

    private function dispatchUjianNotification(ExamSession $examSession): void
    {
        $students = $examSession->classrooms()
            ->with('students')
            ->get()
            ->flatMap(fn ($classroom) => $classroom->students)
            ->unique('id');

        if ($students->isNotEmpty()) {
            Notification::send($students, new UjianDijadwalkanNotification($examSession));
        }
    }

    private function getSubjectsForGuru(User $user): Collection
    {
        $subjects = Subject::whereHas('teachers', fn ($q) => $q->where('users.id', $user->id))
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        if ($subjects->isEmpty()) {
            $subjects = Subject::select('id', 'name', 'code')->orderBy('name')->get();
        }

        return $subjects;
    }

    private function getQuestionBanksForGuru(User $user): Collection
    {
        return QuestionBank::where('user_id', $user->id)
            ->withCount('questions')
            ->with('subject:id,name,code')
            ->orderBy('name')
            ->get();
    }
}
