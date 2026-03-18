<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siswa\SubmitAssignmentRequest;
use App\Models\Assignment;
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
        $student = $request->user();
        $classroomIds = $student->classrooms()->pluck('classrooms.id');
        $subjectId = $request->integer('subject_id') ?: null;

        $query = Assignment::with(['subject', 'classroom'])
            ->whereIn('classroom_id', $classroomIds)
            ->where('is_published', true)
            ->orderBy('deadline_at');

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $assignments = $query->get()->map(function ($assignment) use ($student) {
            $submission = $assignment->submissions()->where('user_id', $student->id)->first();

            return array_merge($assignment->toArray(), [
                'my_submission' => $submission,
                'is_overdue' => $assignment->is_overdue,
            ]);
        });

        return Inertia::render('Siswa/Tugas/Index', [
            'assignments' => $assignments,
            'filters' => ['subject_id' => $subjectId],
        ]);
    }

    public function show(Assignment $assignment, Request $request): Response
    {
        $student = $request->user();

        $inClassroom = $student->classrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
        if (! $inClassroom || ! $assignment->is_published) {
            abort(403);
        }

        $assignment->load(['subject', 'classroom', 'user']);
        $submission = $assignment->submissions()->where('user_id', $student->id)->first();

        return Inertia::render('Siswa/Tugas/Show', [
            'assignment' => $assignment,
            'submission' => $submission,
        ]);
    }

    public function submit(SubmitAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        $student = $request->user();

        $inClassroom = $student->classrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
        if (! $inClassroom || ! $assignment->is_published) {
            abort(403);
        }

        // Prevent re-submit if already graded
        $existing = $assignment->submissions()->where('user_id', $student->id)->first();
        if ($existing && $existing->graded_at) {
            return back()->withErrors(['submit' => 'Tugas sudah dinilai, tidak bisa diubah.']);
        }

        // Check late submission policy
        if (now()->gt($assignment->deadline_at) && ! $assignment->allow_late_submission) {
            return back()->withErrors(['submit' => 'Deadline tugas sudah lewat.']);
        }

        $this->service->submitAssignment($assignment, $student, $request->validated(), $request->file('file'));

        return back()->with('success', 'Tugas berhasil dikumpulkan.');
    }

    public function download(Assignment $assignment, Request $request): mixed
    {
        $student = $request->user();

        $inClassroom = $student->classrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
        if (! $inClassroom || ! $assignment->is_published) {
            abort(403);
        }

        if (! $assignment->file_path || ! Storage::exists($assignment->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::download($assignment->file_path, $assignment->file_original_name);
    }
}
