<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\Subject;
use App\Services\LMS\MaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function __construct(private readonly MaterialService $service) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        $classrooms = $student->classrooms()->with('subjects')->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;

        $materials = null;
        $progress = [];
        $topics = [];
        $progressSummary = null;

        // Default to first classroom/subject if not specified
        if (! $classroomId && $classrooms->isNotEmpty()) {
            $classroomId = $classrooms->first()->id;
        }

        if ($classroomId && $subjectId) {
            // Verify student belongs to classroom
            $inClassroom = $student->classrooms()->where('classrooms.id', $classroomId)->exists();
            if ($inClassroom) {
                $materials = Material::with(['user', 'subject'])
                    ->where('classroom_id', $classroomId)
                    ->where('subject_id', $subjectId)
                    ->published()
                    ->orderBy('topic')
                    ->orderBy('order')
                    ->get();

                $progress = MaterialProgress::where('user_id', $student->id)
                    ->whereIn('material_id', $materials->pluck('id'))
                    ->get()
                    ->keyBy('material_id');

                $topics = $materials->pluck('topic')->filter()->unique()->values();
                $progressSummary = $this->service->getStudentProgress($student, $classroomId, $subjectId);
            }
        }

        return Inertia::render('Siswa/Materi/Index', [
            'classrooms' => $classrooms,
            'materials' => $materials,
            'progress' => $progress,
            'topics' => $topics,
            'progressSummary' => $progressSummary,
            'filters' => [
                'subject_id' => $subjectId,
                'classroom_id' => $classroomId,
            ],
        ]);
    }

    public function show(Material $material, Request $request): Response
    {
        $student = $request->user();

        // Verify student belongs to classroom and material is published
        $inClassroom = $student->classrooms()->where('classrooms.id', $material->classroom_id)->exists();
        if (! $inClassroom || ! $material->is_published) {
            abort(403);
        }

        $material->load(['subject', 'classroom', 'user']);

        $progress = MaterialProgress::where('material_id', $material->id)
            ->where('user_id', $student->id)
            ->first();

        // Get prev/next in same topic
        $siblings = Material::where('classroom_id', $material->classroom_id)
            ->where('subject_id', $material->subject_id)
            ->where('topic', $material->topic)
            ->published()
            ->orderBy('order')
            ->get(['id', 'title', 'order']);

        $currentIndex = $siblings->search(fn ($m) => $m->id === $material->id);
        $prev = $currentIndex > 0 ? $siblings[$currentIndex - 1] : null;
        $next = $currentIndex < $siblings->count() - 1 ? $siblings[$currentIndex + 1] : null;

        return Inertia::render('Siswa/Materi/Show', [
            'material' => $material,
            'progress' => $progress,
            'prev' => $prev,
            'next' => $next,
        ]);
    }

    public function download(Material $material, Request $request): mixed
    {
        $student = $request->user();

        $inClassroom = $student->classrooms()->where('classrooms.id', $material->classroom_id)->exists();
        if (! $inClassroom || ! $material->is_published) {
            abort(403);
        }

        if (! $material->file_path || ! Storage::exists($material->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::download($material->file_path, $material->file_original_name);
    }

    public function complete(Material $material, Request $request): RedirectResponse
    {
        $student = $request->user();

        $inClassroom = $student->classrooms()->where('classrooms.id', $material->classroom_id)->exists();
        if (! $inClassroom || ! $material->is_published) {
            abort(403);
        }

        $this->service->markComplete($material, $student);

        return back()->with('success', 'Materi ditandai selesai.');
    }
}
