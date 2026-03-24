<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentAnswer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PersistAnswersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Scan Redis keys matching exam:*:student:*:answers
        $cursor = '0';
        do {
            [$cursor, $keys] = Redis::scan($cursor, ['match' => 'exam:*:student:*:answers', 'count' => 100]);

            if (! is_array($keys)) {
                continue;
            }

            foreach ($keys as $key) {
                $this->persistKey($key);
            }
        } while ($cursor !== '0');
    }

    private function persistKey(string $key): void
    {
        // Parse key: exam:{sessionId}:student:{userId}:answers
        if (! preg_match('/exam:(\d+):student:(\d+):answers/', $key, $matches)) {
            return;
        }

        $answersJson = Redis::get($key);
        if (! $answersJson) {
            return;
        }

        $answers = json_decode($answersJson, true);
        if (! is_array($answers) || empty($answers)) {
            Log::warning('PersistAnswersJob: corrupted Redis data', [
                'key' => $key,
                'raw_data' => is_string($answersJson) ? mb_substr($answersJson, 0, 200) : null,
            ]);

            return;
        }

        // Find the attempt
        $attempt = \App\Models\ExamAttempt::where('exam_session_id', (int) $matches[1])
            ->where('user_id', (int) $matches[2])
            ->where('status', \App\Enums\ExamAttemptStatus::InProgress)
            ->first();

        if (! $attempt) {
            return;
        }

        $now = now()->toDateTimeString();
        $values = [];

        foreach ($answers as $questionId => $answer) {
            $values[] = [
                'exam_attempt_id' => $attempt->id,
                'question_id' => (int) $questionId,
                'answer' => $answer,
                'answered_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($values)) {
            StudentAnswer::upsert(
                $values,
                ['exam_attempt_id', 'question_id'],
                ['answer', 'answered_at', 'updated_at']
            );
        }
    }
}
