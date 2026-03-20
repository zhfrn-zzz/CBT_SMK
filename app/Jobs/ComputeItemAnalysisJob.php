<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExamSession;
use App\Models\ItemAnalysisCache;
use App\Services\Analytics\ItemAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class ComputeItemAnalysisJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public function __construct(public readonly ExamSession $examSession) {}

    public function handle(ItemAnalysisService $service): void
    {
        $result = $service->analyzeExamSession($this->examSession);

        ItemAnalysisCache::updateOrCreate(
            ['exam_session_id' => $this->examSession->id],
            [
                'analysis_data' => $result,
                'computed_at' => now(),
            ]
        );
    }
}
