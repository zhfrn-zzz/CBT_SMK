<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Services\Exam\ExamAttemptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GradeExamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}

    public function handle(ExamAttemptService $attemptService): void
    {
        // Idempotency: skip if already graded
        if ($this->attempt->status === ExamAttemptStatus::Graded) {
            return;
        }

        $attemptService->autoGrade($this->attempt);
    }
}
