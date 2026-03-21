<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CleanupOrphanedFilesJob;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Material;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    public function index(Request $request): Response
    {
        $breakdown = $this->getCategoryBreakdown();
        $totalUsed = array_sum(array_column($breakdown, 'size'));
        $topFiles = $this->getTopFiles(10);
        $orphanedFiles = $this->scanOrphanedFiles();

        return Inertia::render('Admin/Storage/Index', [
            'totalUsed' => $totalUsed,
            'breakdown' => $breakdown,
            'topFiles' => $topFiles,
            'orphanedFiles' => $orphanedFiles,
        ]);
    }

    public function scan(Request $request): Response
    {
        $orphanedFiles = $this->scanOrphanedFiles();

        return Inertia::render('Admin/Storage/Index', [
            'totalUsed' => array_sum(array_column($this->getCategoryBreakdown(), 'size')),
            'breakdown' => $this->getCategoryBreakdown(),
            'topFiles' => $this->getTopFiles(10),
            'orphanedFiles' => $orphanedFiles,
        ]);
    }

    public function cleanup(Request $request): RedirectResponse
    {
        CleanupOrphanedFilesJob::dispatch($request->user()->id);

        return back()->with('success', 'Proses cleanup file orphan sedang berjalan di background. Anda akan mendapat notifikasi saat selesai.');
    }

    /**
     * @return array<int, array{category: string, label: string, size: int, count: int}>
     */
    private function getCategoryBreakdown(): array
    {
        $materials = Material::whereNotNull('file_path')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size')
            ->first();

        $assignmentSize = $this->calculateDiskSize('assignments');
        $assignmentCount = Assignment::whereNotNull('file_path')->count();

        $submissionSize = $this->calculateDiskSize('submissions');
        $submissionCount = AssignmentSubmission::whereNotNull('file_path')->count();

        $questionSize = $this->calculateDiskSize('questions');
        $questionCount = Question::whereNotNull('media_path')->count();

        return [
            [
                'category' => 'materials',
                'label' => 'Materi',
                'size' => (int) $materials->total_size,
                'count' => (int) $materials->count,
            ],
            [
                'category' => 'questions',
                'label' => 'Soal (Gambar)',
                'size' => $questionSize,
                'count' => $questionCount,
            ],
            [
                'category' => 'assignments',
                'label' => 'Tugas',
                'size' => $assignmentSize,
                'count' => $assignmentCount,
            ],
            [
                'category' => 'submissions',
                'label' => 'Submission Siswa',
                'size' => $submissionSize,
                'count' => $submissionCount,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, path: string, size: int, category: string, created_at: string|null}>
     */
    private function getTopFiles(int $limit): array
    {
        $files = [];

        // Materials — use DB file_size since it's stored
        $materials = Material::whereNotNull('file_path')
            ->orderByDesc('file_size')
            ->limit($limit)
            ->get(['file_path', 'file_original_name', 'file_size', 'created_at']);

        foreach ($materials as $material) {
            $files[] = [
                'name' => $material->file_original_name ?? basename($material->file_path),
                'path' => $material->file_path,
                'size' => (int) $material->file_size,
                'category' => 'Materi',
                'created_at' => $material->created_at?->toIso8601String(),
            ];
        }

        // Assignments — scan disk for size
        $assignments = Assignment::whereNotNull('file_path')
            ->get(['file_path', 'file_original_name', 'created_at']);

        foreach ($assignments as $assignment) {
            $size = Storage::disk('public')->exists($assignment->file_path)
                ? Storage::disk('public')->size($assignment->file_path)
                : 0;
            $files[] = [
                'name' => $assignment->file_original_name ?? basename($assignment->file_path),
                'path' => $assignment->file_path,
                'size' => $size,
                'category' => 'Tugas',
                'created_at' => $assignment->created_at?->toIso8601String(),
            ];
        }

        // Submissions — scan disk for size
        $submissions = AssignmentSubmission::whereNotNull('file_path')
            ->get(['file_path', 'file_original_name', 'created_at']);

        foreach ($submissions as $submission) {
            $size = Storage::disk('public')->exists($submission->file_path)
                ? Storage::disk('public')->size($submission->file_path)
                : 0;
            $files[] = [
                'name' => $submission->file_original_name ?? basename($submission->file_path),
                'path' => $submission->file_path,
                'size' => $size,
                'category' => 'Submission',
                'created_at' => $submission->created_at?->toIso8601String(),
            ];
        }

        // Questions — scan disk for size
        $questions = Question::whereNotNull('media_path')
            ->get(['media_path', 'created_at']);

        foreach ($questions as $question) {
            $size = Storage::disk('public')->exists($question->media_path)
                ? Storage::disk('public')->size($question->media_path)
                : 0;
            $files[] = [
                'name' => basename($question->media_path),
                'path' => $question->media_path,
                'size' => $size,
                'category' => 'Soal',
                'created_at' => $question->created_at?->toIso8601String(),
            ];
        }

        // Sort by size descending and take top N
        usort($files, fn (array $a, array $b): int => $b['size'] <=> $a['size']);

        return array_slice($files, 0, $limit);
    }

    /**
     * @return array<int, array{path: string, size: int, last_modified: string}>
     */
    private function scanOrphanedFiles(): array
    {
        $dbPaths = collect();

        // Collect all known file paths from DB
        Material::whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => $dbPaths->push($p));
        Assignment::whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => $dbPaths->push($p));
        AssignmentSubmission::whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => $dbPaths->push($p));
        Question::whereNotNull('media_path')->pluck('media_path')->each(fn ($p) => $dbPaths->push($p));

        $dbPathsSet = $dbPaths->flip();
        $orphaned = [];
        $directories = ['materials', 'questions', 'assignments', 'submissions'];

        foreach ($directories as $dir) {
            $diskFiles = Storage::disk('public')->files($dir);
            foreach ($diskFiles as $diskFile) {
                if (! $dbPathsSet->has($diskFile)) {
                    $orphaned[] = [
                        'path' => $diskFile,
                        'size' => Storage::disk('public')->size($diskFile),
                        'last_modified' => date('c', Storage::disk('public')->lastModified($diskFile)),
                    ];
                }
            }
        }

        return $orphaned;
    }

    private function calculateDiskSize(string $directory): int
    {
        $total = 0;
        $files = Storage::disk('public')->files($directory);
        foreach ($files as $file) {
            $total += Storage::disk('public')->size($file);
        }

        return $total;
    }
}
