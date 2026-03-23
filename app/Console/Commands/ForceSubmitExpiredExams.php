<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Services\Exam\ExamAttemptService;
use Illuminate\Console\Command;

class ForceSubmitExpiredExams extends Command
{
    protected $signature = 'exam:force-submit-expired';

    protected $description = 'Force submit expired exam attempts';

    public function handle(ExamAttemptService $attemptService): int
    {
        $count = 0;

        ExamAttempt::where('status', ExamAttemptStatus::InProgress)
            ->with('examSession')
            ->chunkById(50, function ($attempts) use ($attemptService, &$count) {
                foreach ($attempts as $attempt) {
                    if ($attempt->isExpired()) {
                        $attemptService->submitExam($attempt, true);
                        $count++;
                    }
                }
            });

        $this->info("Force submitted {$count} expired exam(s).");

        return self::SUCCESS;
    }
}
