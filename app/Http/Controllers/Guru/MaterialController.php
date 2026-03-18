<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreMaterialRequest;
use App\Http\Requests\Guru\UpdateMaterialRequest;
use App\Models\Classroom;
use App\Models\Material;
use App\Models\Subject;
use App\Models\TeachingAssignment;
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
        $user = $request->user();

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;
        $topic = $request->string('topic')->value() ?: null;

        $materials = null;
        $topics = [];
        $progressStats = null;

        if ($subjectId && $classroomId) {
            $materials = $this->service->getForClassroom($classroomId, $subjectId, $topic)
                ->withQueryString();
            $topics = $this->service->getTopics($classroomId, $subjectId);
            $progressStats = $this->service->getProgressOverview($classroomId, $subjectId);
        }

        return Inertia::render('Guru/Materi/Index', [
            'assignments' => $assignments,
            'materials' => $materials,
            'topics' => $topics,
            'progressStats' => $progressStats,
            'filters' => [
                'subject_id' => $subjectId,
                'classroom_id' => $classroomId,
                'topic' => $topic,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->get();

        return Inertia::render('Guru/Materi/Create', [
            'assignments' => $assignments,
        ]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        $data = $request->except(['file']);
        $data['user_id'] = $request->user()->id;
        $data['order'] = $data['order'] ?? 0;
        $data['is_published'] = $data['is_published'] ?? true;

        $material = $this->service->create($data, $request->file('file'));

        return redirect()->route('guru.materi.show', $material)
            ->with('success', 'Materi berhasil dibuat.');
    }

    public function show(Material $material): Response
    {
        $this->authorize('update', $material);

        $material->load(['subject', 'classroom', 'user']);

        $classroom = $material->classroom;
        $totalStudents = $classroom->students()->count();

        $progressData = $material->progress()
            ->with('user')
            ->get()
            ->keyBy('user_id');

        $students = $classroom->students()
            ->orderBy('name')
            ->get()
            ->map(function ($student) use ($progressData) {
                $progress = $progressData->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'username' => $student->username,
                    'is_completed' => $progress?->is_completed ?? false,
                    'completed_at' => $progress?->completed_at,
                ];
            });

        $completionCount = $progressData->where('is_completed', true)->count();

        return Inertia::render('Guru/Materi/Show', [
            'material' => $material,
            'students' => $students,
            'completion_count' => $completionCount,
            'total_students' => $totalStudents,
        ]);
    }

    public function edit(Material $material, Request $request): Response
    {
        $this->authorize('update', $material);

        $material->load(['subject', 'classroom']);

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $request->user()->id)
            ->get();

        $topics = $this->service->getTopics($material->classroom_id, $material->subject_id);

        return Inertia::render('Guru/Materi/Edit', [
            'material' => $material,
            'assignments' => $assignments,
            'topics' => $topics,
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $data = $request->except(['file']);

        $this->service->update($material, $data, $request->file('file'));

        return redirect()->route('guru.materi.show', $material)
            ->with('success', 'Materi berhasil diperbarui.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $this->authorize('delete', $material);

        $this->service->delete($material);

        return redirect()->route('guru.materi.index')
            ->with('success', 'Materi berhasil dihapus.');
    }

    public function download(Material $material): mixed
    {
        $this->authorize('update', $material);

        if (! $material->file_path || ! Storage::exists($material->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::download($material->file_path, $material->file_original_name);
    }

    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:materials,id'],
        ]);

        $this->service->reorder($request->input('ordered_ids'));

        return back()->with('success', 'Urutan materi berhasil diperbarui.');
    }
}
