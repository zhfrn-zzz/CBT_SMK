<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Services\Exam\ProctorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProctorController extends Controller
{
    public function __construct(
        private readonly ProctorService $proctorService,
    ) {}

    public function show(ExamSession $ujian): Response
    {
        $this->authorize('view', $ujian);

        $data = $this->proctorService->getDashboardData($ujian);

        return Inertia::render('Guru/Ujian/Proctor', $data);
    }

    public function extendTime(Request $request, ExamSession $ujian): RedirectResponse
    {
        $this->authorize('update', $ujian);

        $request->validate([
            'attempt_id' => ['required', 'exists:exam_attempts,id'],
            'additional_minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $attempt = ExamAttempt::where('id', $request->input('attempt_id'))
            ->where('exam_session_id', $ujian->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->firstOrFail();

        $this->proctorService->extendTime(
            $attempt,
            (int) $request->input('additional_minutes'),
            $request->user(),
        );

        return back()->with('success', "Waktu berhasil ditambah {$request->input('additional_minutes')} menit.");
    }

    public function terminate(Request $request, ExamSession $ujian): RedirectResponse
    {
        $this->authorize('update', $ujian);

        $request->validate([
            'attempt_id' => ['required', 'exists:exam_attempts,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $attempt = ExamAttempt::where('id', $request->input('attempt_id'))
            ->where('exam_session_id', $ujian->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->firstOrFail();

        $this->proctorService->terminate(
            $attempt,
            $request->user(),
            $request->input('reason', 'Diterminasi oleh pengawas'),
        );

        return back()->with('success', 'Ujian siswa berhasil diterminasi.');
    }

    public function invalidateQuestion(Request $request, ExamSession $ujian): RedirectResponse
    {
        $this->authorize('update', $ujian);

        $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
        ]);

        $affected = $this->proctorService->invalidateQuestion(
            $ujian,
            (int) $request->input('question_id'),
            $request->user(),
        );

        return back()->with('success', "Soal berhasil dibatalkan. {$affected} jawaban diperbarui.");
    }
}
