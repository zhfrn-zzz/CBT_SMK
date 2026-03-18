<?php

declare(strict_types=1);

namespace App\Services\LMS;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssignmentService
{
    public function getForTeacher(User $teacher, ?int $subjectId = null, ?int $classroomId = null): LengthAwarePaginator
    {
        $query = Assignment::with(['subject', 'classroom'])
            ->where('user_id', $teacher->id)
            ->withCount([
                'submissions',
                'submissions as graded_count' => fn ($q) => $q->whereNotNull('graded_at'),
            ])
            ->orderByDesc('deadline_at');

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }
        if ($classroomId) {
            $query->where('classroom_id', $classroomId);
        }

        return $query->paginate(20);
    }

    public function create(array $data, ?UploadedFile $file = null): Assignment
    {
        if ($file) {
            $data = array_merge($data, $this->handleAttachmentUpload($file, 'temp'));
        }

        $assignment = Assignment::create($data);

        if ($file && isset($data['file_path'])) {
            // Move file to proper path using assignment ID
            $newPath = $this->moveFile($data['file_path'], $assignment->id);
            $assignment->update(['file_path' => $newPath]);
        }

        return $assignment->fresh();
    }

    public function update(Assignment $assignment, array $data, ?UploadedFile $file = null): Assignment
    {
        if ($file) {
            if ($assignment->file_path) {
                Storage::delete($assignment->file_path);
            }
            $uploaded = $this->handleAttachmentUpload($file, (string) $assignment->id);
            $data = array_merge($data, $uploaded);
        }

        $assignment->update($data);

        return $assignment->fresh();
    }

    public function delete(Assignment $assignment): void
    {
        // Delete teacher attachment
        if ($assignment->file_path) {
            Storage::delete($assignment->file_path);
        }

        // Delete submission files
        foreach ($assignment->submissions as $submission) {
            if ($submission->file_path) {
                Storage::delete($submission->file_path);
            }
        }

        $assignment->delete();
    }

    public function getSubmissions(Assignment $assignment): Collection
    {
        return $assignment->submissions()
            ->with('user')
            ->orderBy('submitted_at')
            ->get();
    }

    public function gradeSubmission(
        AssignmentSubmission $submission,
        float $score,
        ?string $feedback,
        User $grader
    ): void {
        $submission->update([
            'score' => $score,
            'feedback' => $feedback,
            'graded_at' => now(),
            'graded_by' => $grader->id,
        ]);
    }

    public function getSubmissionStats(Assignment $assignment): array
    {
        $totalStudents = $assignment->classroom->students()->count();
        $submittedCount = $assignment->submissions()->count();
        $gradedCount = $assignment->submissions()->whereNotNull('graded_at')->count();
        $lateCount = $assignment->submissions()->where('is_late', true)->count();

        return [
            'total_students' => $totalStudents,
            'submitted' => $submittedCount,
            'not_submitted' => $totalStudents - $submittedCount,
            'graded' => $gradedCount,
            'late' => $lateCount,
        ];
    }

    public function submitAssignment(
        Assignment $assignment,
        User $student,
        array $data,
        ?UploadedFile $file = null
    ): AssignmentSubmission {
        $isLate = now()->gt($assignment->deadline_at);

        $submissionData = [
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => $data['content'] ?? null,
            'submitted_at' => now(),
            'is_late' => $isLate,
        ];

        $existing = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->first();

        if ($file) {
            // Delete old file if re-submitting
            if ($existing?->file_path) {
                Storage::delete($existing->file_path);
            }
            $uploaded = $this->handleSubmissionUpload($file, $assignment->id, $student->id);
            $submissionData = array_merge($submissionData, $uploaded);
        }

        if ($existing) {
            $existing->update($submissionData);

            return $existing->fresh();
        }

        return AssignmentSubmission::create($submissionData);
    }

    private function handleAttachmentUpload(UploadedFile $file, string $assignmentId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $filename = now()->timestamp.'_'.$slug.'.'.$extension;
        $path = "assignments/attachments/{$assignmentId}";

        return [
            'file_path' => $file->storeAs($path, $filename),
            'file_original_name' => $originalName,
        ];
    }

    private function handleSubmissionUpload(UploadedFile $file, int $assignmentId, int $userId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $filename = now()->timestamp.'_'.$slug.'.'.$extension;
        $path = "assignments/submissions/{$assignmentId}/{$userId}";

        return [
            'file_path' => $file->storeAs($path, $filename),
            'file_original_name' => $originalName,
        ];
    }

    private function moveFile(string $oldPath, int $assignmentId): string
    {
        $newPath = str_replace('/temp/', "/{$assignmentId}/", $oldPath);
        if (Storage::exists($oldPath)) {
            Storage::move($oldPath, $newPath);
        }

        return $newPath;
    }
}
