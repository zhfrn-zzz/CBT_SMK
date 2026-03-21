<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FileManagerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $type = $request->input('type');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        $files = $this->getGuruFiles($user->id, $type, $sort, $direction);

        return Inertia::render('Guru/FileManager/Index', [
            'files' => $files,
            'filters' => [
                'type' => $type,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        $user = $request->user();

        return match ($type) {
            'material' => $this->deleteMaterialFile($user->id, $id),
            'assignment' => $this->deleteAssignmentFile($user->id, $id),
            'question' => $this->deleteQuestionMedia($user->id, $id),
            default => back()->with('error', 'Tipe file tidak valid.'),
        };
    }

    /**
     * @return array<int, array{id: int, type: string, name: string, path: string, size: int, is_used: bool, usage_info: string, created_at: string|null}>
     */
    private function getGuruFiles(int $userId, ?string $type, string $sort, string $direction): array
    {
        $files = [];

        if (! $type || $type === 'material') {
            $materials = Material::where('user_id', $userId)
                ->whereNotNull('file_path')
                ->get(['id', 'file_path', 'file_original_name', 'file_size', 'is_published', 'created_at']);

            foreach ($materials as $material) {
                $files[] = [
                    'id' => $material->id,
                    'type' => 'material',
                    'type_label' => 'Materi',
                    'name' => $material->file_original_name ?? basename($material->file_path),
                    'path' => $material->file_path,
                    'size' => (int) $material->file_size,
                    'is_used' => (bool) $material->is_published,
                    'usage_info' => $material->is_published ? 'Terpublikasi' : 'Tidak terpublikasi',
                    'created_at' => $material->created_at?->toIso8601String(),
                ];
            }
        }

        if (! $type || $type === 'assignment') {
            $assignments = Assignment::where('user_id', $userId)
                ->whereNotNull('file_path')
                ->withCount('submissions')
                ->get(['id', 'file_path', 'file_original_name', 'is_published', 'created_at']);

            foreach ($assignments as $assignment) {
                $size = Storage::disk('public')->exists($assignment->file_path)
                    ? Storage::disk('public')->size($assignment->file_path)
                    : 0;
                $hasSubmissions = $assignment->submissions_count > 0;
                $files[] = [
                    'id' => $assignment->id,
                    'type' => 'assignment',
                    'type_label' => 'Tugas',
                    'name' => $assignment->file_original_name ?? basename($assignment->file_path),
                    'path' => $assignment->file_path,
                    'size' => $size,
                    'is_used' => $hasSubmissions || $assignment->is_published,
                    'usage_info' => $hasSubmissions
                        ? "Memiliki {$assignment->submissions_count} submission"
                        : ($assignment->is_published ? 'Terpublikasi' : 'Tidak terpublikasi'),
                    'created_at' => $assignment->created_at?->toIso8601String(),
                ];
            }
        }

        if (! $type || $type === 'question') {
            $questionBankIds = QuestionBank::where('user_id', $userId)->pluck('id');
            $questions = Question::whereIn('question_bank_id', $questionBankIds)
                ->whereNotNull('media_path')
                ->with('questionBank:id,name')
                ->get(['id', 'question_bank_id', 'media_path', 'created_at']);

            foreach ($questions as $question) {
                $size = Storage::disk('public')->exists($question->media_path)
                    ? Storage::disk('public')->size($question->media_path)
                    : 0;
                $files[] = [
                    'id' => $question->id,
                    'type' => 'question',
                    'type_label' => 'Soal',
                    'name' => basename($question->media_path),
                    'path' => $question->media_path,
                    'size' => $size,
                    'is_used' => true,
                    'usage_info' => 'Bank: ' . ($question->questionBank->name ?? '-'),
                    'created_at' => $question->created_at?->toIso8601String(),
                ];
            }
        }

        // Sort
        $sortKey = match ($sort) {
            'size' => 'size',
            'name' => 'name',
            default => 'created_at',
        };

        usort($files, function (array $a, array $b) use ($sortKey, $direction): int {
            $cmp = $a[$sortKey] <=> $b[$sortKey];

            return $direction === 'asc' ? $cmp : -$cmp;
        });

        return $files;
    }

    private function deleteMaterialFile(int $userId, int $materialId): RedirectResponse
    {
        $material = Material::where('user_id', $userId)->findOrFail($materialId);

        if ($material->is_published) {
            return back()->with('error', 'Tidak dapat menghapus file materi yang masih terpublikasi. Nonaktifkan materi terlebih dahulu.');
        }

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->update(['file_path' => null, 'file_original_name' => null, 'file_size' => null]);

        return back()->with('success', 'File materi berhasil dihapus.');
    }

    private function deleteAssignmentFile(int $userId, int $assignmentId): RedirectResponse
    {
        $assignment = Assignment::where('user_id', $userId)->findOrFail($assignmentId);

        $hasSubmissions = $assignment->submissions()->exists();
        if ($hasSubmissions) {
            return back()->with('error', 'Tidak dapat menghapus file tugas yang sudah memiliki submission siswa.');
        }

        if ($assignment->is_published) {
            return back()->with('error', 'Tidak dapat menghapus file tugas yang masih terpublikasi.');
        }

        if ($assignment->file_path && Storage::disk('public')->exists($assignment->file_path)) {
            Storage::disk('public')->delete($assignment->file_path);
        }

        $assignment->update(['file_path' => null, 'file_original_name' => null]);

        return back()->with('success', 'File tugas berhasil dihapus.');
    }

    private function deleteQuestionMedia(int $userId, int $questionId): RedirectResponse
    {
        $questionBankIds = QuestionBank::where('user_id', $userId)->pluck('id');
        $question = Question::whereIn('question_bank_id', $questionBankIds)->findOrFail($questionId);

        if ($question->media_path && Storage::disk('public')->exists($question->media_path)) {
            Storage::disk('public')->delete($question->media_path);
        }

        $question->update(['media_path' => null]);

        return back()->with('success', 'Media soal berhasil dihapus.');
    }
}
