<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\ExamStatus;
use App\Models\ExamSession;
use Illuminate\Support\Str;

class ExamSessionService
{
    /**
     * Generate unique 6-character token.
     */
    public function generateToken(): string
    {
        do {
            $token = strtoupper(Str::random(6));
        } while (ExamSession::where('token', $token)->exists());

        return $token;
    }

    /**
     * Update status berdasarkan waktu.
     */
    public function syncStatus(ExamSession $examSession): void
    {
        $now = now();

        $newStatus = match (true) {
            $examSession->status === ExamStatus::Draft => ExamStatus::Draft,
            $examSession->status === ExamStatus::Archived => ExamStatus::Archived,
            $now->lt($examSession->starts_at) => ExamStatus::Scheduled,
            $now->gte($examSession->starts_at) && $now->lte($examSession->ends_at) => ExamStatus::Active,
            $now->gt($examSession->ends_at) => ExamStatus::Completed,
            default => $examSession->status,
        };

        if ($newStatus !== $examSession->status) {
            $examSession->update(['status' => $newStatus]);
        }
    }
}
