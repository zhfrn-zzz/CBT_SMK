<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\ExamSessionRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use App\Services\Exam\ExamSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ...\Illuminate\Support\Arr::except($data, ['classroom_ids']),
            'user_id' => $request->user()->id,
            'token' => $this->sessionService->generateToken(),
            'status' => ExamStatus::Scheduled,
        ]);

        $examSession->classrooms()->sync($data['classroom_ids']);

        return redirect()->route('guru.ujian.show', $examSession)
            ->with('success', 'Sesi ujian berhasil dibuat.');
    }

    public function show(ExamSession $ujian): Response
    {
        $this->authorize('view', $ujian);
        $this->sessionService->syncStatus($ujian);

        $ujian->load([
            'subject',
            'questionBank.questions',
            'classrooms.department',
            'academicYear',
            'attempts.user',
        ]);

        return Inertia::render('Guru/Ujian/Show', [
            'examSession' => $ujian,
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

        $ujian->update(\Illuminate\Support\Arr::except($data, ['classroom_ids']));
        $ujian->classrooms()->sync($data['classroom_ids']);

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
