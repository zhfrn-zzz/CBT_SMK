<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Notifications\DeadlineTugasNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendDeadlineRemindersCommand extends Command
{
    protected $signature = 'notifications:send-deadline-reminders';

    protected $description = 'Send deadline reminders to students with assignments due in 24 hours';

    public function handle(): int
    {
        $assignments = Assignment::query()
            ->where('is_published', true)
            ->whereBetween('deadline_at', [now()->addHours(23), now()->addHours(25)])
            ->with(['classroom.students'])
            ->get();

        $count = 0;

        foreach ($assignments as $assignment) {
            $classroom = $assignment->classroom;

            if (! $classroom) {
                continue;
            }

            // Get students who haven't submitted
            $submittedUserIds = $assignment->submissions()->pluck('user_id');

            $students = $classroom->students()
                ->whereNotIn('users.id', $submittedUserIds)
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            Notification::send($students, new DeadlineTugasNotification($assignment));
            $count += $students->count();
        }

        $this->info("Sent deadline reminders to {$count} students.");

        return self::SUCCESS;
    }
}
