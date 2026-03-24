<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Material;
use App\Models\Question;
use App\Models\User;
use App\Notifications\CleanupCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private readonly int $userId,
    ) {}

    public function handle(): void
    {
        $dbPaths = collect();

        Material::whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => $dbPaths->push($p));
        Assignment::whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => $dbPaths->push($p));
        AssignmentSubmission::whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => $dbPaths->push($p));
        Question::whereNotNull('media_path')->pluck('media_path')->each(fn ($p) => $dbPaths->push($p));

        $dbPathsSet = $dbPaths->flip();
        $directories = ['materials', 'questions', 'assignments', 'submissions'];
        $deletedCount = 0;
        $freedBytes = 0;

        foreach ($directories as $dir) {
            if (! Storage::disk('public')->exists($dir)) {
                continue;
            }

            $diskFiles = Storage::disk('public')->allFiles($dir);
            foreach ($diskFiles as $diskFile) {
                if (! $dbPathsSet->has($diskFile)) {
                    $size = Storage::disk('public')->size($diskFile);
                    Storage::disk('public')->delete($diskFile);
                    $deletedCount++;
                    $freedBytes += $size;
                }
            }
        }

        Log::info('Cleanup orphaned files completed', [
            'deleted_count' => $deletedCount,
            'freed_bytes' => $freedBytes,
            'triggered_by' => $this->userId,
        ]);

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new CleanupCompletedNotification($deletedCount, $freedBytes));
        }
    }
}
