<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreAssignmentRequest;
use App\Http\Requests\Guru\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\TeachingAssignment;
use App\Services\LMS\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;

        $tugasList = $this->service->getForTeacher($user, $subjectId, $classroomId)
            ->withQueryString();

        return Inertia::render('Guru/Tugas/Index', [
            'assignments' => $tugasList,
            'teachingAssignments' => $assignments,
            'filters' => ['subject_id' => $subjectId, 'classroom_id' => $classroomId],
        ]);
    }

    public function create(Request $request): Response
    {
        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $request->user()->id)
            ->get();

        return Inertia::render('Guru/Tugas/Create', [
            'teachingAssignments' => $assignments,
        ]);
    }

    public function store(StoreAssignmentRequest $request): RedirectResponse
    {
        $data = $request->except(['file']);
        $data['user_id'] = $request->user()->id;
        $data['is_published'] = $data['is_published'] ?? true;
        $data['allow_late_submission'] = $data['allow_late_submission'] ?? false;

        $assignment = $this->service->create($data, $request->file('file'));

        return redirect()->route('guru.tugas.show', $assignment)
            ->with('success', 'Tugas berhasil dibuat.');
    }

    public function show(Assignment $assignment, Request $request): Response
    {
        $this->authorize('update', $assignment);

        $assignment->load(['subject', 'classroom']);
        $stats = $this->service->getSubmissionStats($assignment);
        $submissions = $this->service->getSubmissions($assignment);

        $students = $assignment->classroom->students()->orderBy('name')->get();

        return Inertia::render('Guru/Tugas/Show', [
            'assignment' => $assignment,
            'submissions' => $submissions,
            'students' => $students,
            'stats' => $stats,
        ]);
    }

    public function edit(Assignment $assignment, Request $request): Response
    {
        $this->authorize('update', $assignment);

        $assignment->load(['subject', 'classroom']);

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $request->user()->id)
            ->get();

        return Inertia::render('Guru/Tugas/Edit', [
            'assignment' => $assignment,
            'teachingAssignments' => $assignments,
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $data = $request->except(['file']);
        $this->service->update($assignment, $data, $request->file('file'));

        return redirect()->route('guru.tugas.show', $assignment)
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        $this->service->delete($assignment);

        return redirect()->route('guru.tugas.index')
            ->with('success', 'Tugas berhasil dihapus.');
    }

    public function download(Assignment $assignment): mixed
    {
        $this->authorize('update', $assignment);

        if (! $assignment->file_path || ! Storage::exists($assignment->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::download($assignment->file_path, $assignment->file_original_name);
    }

    public function grade(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $this->authorize('grade', $submission->assignment);

        $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:' . $submission->assignment->max_score],
            'feedback' => ['nullable', 'string'],
        ]);

        $this->service->gradeSubmission(
            $submission,
            (float) $request->input('score'),
            $request->input('feedback'),
            $request->user()
        );

        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    public function downloadSubmission(AssignmentSubmission $submission): mixed
    {
        $this->authorize('grade', $submission->assignment);

        if (! $submission->file_path || ! Storage::exists($submission->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::download($submission->file_path, $submission->file_original_name);
    }
}
