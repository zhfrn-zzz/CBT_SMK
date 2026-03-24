<?php

declare(strict_types=1);

namespace App\Services\LMS;

use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MaterialService
{
    public function getForClassroom(int $classroomId, int $subjectId, ?string $topic = null): LengthAwarePaginator
    {
        $query = Material::with(['user', 'subject', 'classroom'])
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->orderBy('topic')
            ->orderBy('order');

        if ($topic !== null && $topic !== '') {
            $query->where('topic', $topic);
        }

        return $query->paginate(50);
    }

    public function create(array $data, ?UploadedFile $file = null): Material
    {
        if ($file) {
            $data = array_merge($data, $this->handleFileUpload($file, $data['subject_id'], $data['classroom_id']));
        }

        return Material::create($data);
    }

    public function update(Material $material, array $data, ?UploadedFile $file = null): Material
    {
        if ($file) {
            // Delete old file
            if ($material->file_path) {
                Storage::delete($material->file_path);
            }
            $data = array_merge($data, $this->handleFileUpload($file, $data['subject_id'] ?? $material->subject_id, $data['classroom_id'] ?? $material->classroom_id));
        }

        $material->update($data);

        return $material->fresh();
    }

    public function delete(Material $material): void
    {
        if ($material->file_path) {
            Storage::delete($material->file_path);
        }

        $material->delete();
    }

    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $order => $id) {
            Material::where('id', $id)->update(['order' => $order]);
        }
    }

    public function getProgressOverview(int $classroomId, int $subjectId): Collection
    {
        return Material::where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('is_published', true)
            ->withCount(['progress as completion_count' => fn ($q) => $q->where('is_completed', true)])
            ->get();
    }

    public function getTopics(int $classroomId, int $subjectId): array
    {
        return Material::where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->whereNotNull('topic')
            ->distinct()
            ->orderBy('topic')
            ->pluck('topic')
            ->toArray();
    }

    public function markComplete(Material $material, User $student): MaterialProgress
    {
        // Verify student belongs to the material's classroom
        $inClassroom = $student->classrooms()->where('classrooms.id', $material->classroom_id)->exists();
        if (! $inClassroom) {
            throw new \RuntimeException('Anda tidak terdaftar di kelas ini.');
        }

        $progress = MaterialProgress::firstOrNew([
            'material_id' => $material->id,
            'user_id' => $student->id,
        ]);

        if (! $progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
            $progress->save();
        }

        return $progress;
    }

    public function getStudentProgress(User $student, int $classroomId, int $subjectId): array
    {
        $totalMaterials = Material::where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('is_published', true)
            ->count();

        if ($totalMaterials === 0) {
            return ['total' => 0, 'completed' => 0, 'percentage' => 0];
        }

        $completed = MaterialProgress::whereHas('material', fn ($q) => $q
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('is_published', true)
        )
            ->where('user_id', $student->id)
            ->where('is_completed', true)
            ->count();

        return [
            'total' => $totalMaterials,
            'completed' => $completed,
            'percentage' => round($completed / $totalMaterials * 100),
        ];
    }

    private function handleFileUpload(UploadedFile $file, int $subjectId, int $classroomId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $filename = now()->timestamp.'_'.$slug.'.'.$extension;
        $path = "materials/{$subjectId}/{$classroomId}";

        $storedPath = $file->storeAs($path, $filename);

        return [
            'file_path' => $storedPath,
            'file_original_name' => $originalName,
            'file_size' => $file->getSize(),
        ];
    }
}
