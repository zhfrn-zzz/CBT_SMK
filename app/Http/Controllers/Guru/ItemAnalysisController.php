<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeItemAnalysisJob;
use App\Models\ExamSession;
use App\Services\Analytics\ItemAnalysisService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemAnalysisController extends Controller
{
    public function __construct(private readonly ItemAnalysisService $itemAnalysisService) {}

    public function show(ExamSession $examSession): Response
    {
        $this->authorize('view', $examSession);

        $examSession->load('subject');
        $analysis = $this->itemAnalysisService->getOrComputeAnalysis($examSession);
        $attemptCount = $examSession->attempts()
            ->whereIn('status', ['submitted', 'graded'])
            ->count();

        return Inertia::render('Guru/Penilaian/ItemAnalysis', [
            'examSession' => [
                'id' => $examSession->id,
                'name' => $examSession->name,
                'subject' => $examSession->subject->name,
            ],
            'analysis' => $analysis,
            'attemptCount' => $attemptCount,
        ]);
    }

    public function refresh(ExamSession $examSession): RedirectResponse
    {
        $this->authorize('view', $examSession);

        ComputeItemAnalysisJob::dispatch($examSession);

        return back()->with('success', 'Analisis soal sedang diproses.');
    }
}
