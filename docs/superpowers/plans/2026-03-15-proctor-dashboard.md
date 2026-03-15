# Proctor Dashboard Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a real-time proctor dashboard for guru to monitor students during exams, with manual override capabilities and broadcasting events via Laravel Reverb WebSocket.

**Architecture:** Broadcasting events are dispatched from existing student exam flow (start, save, submit, log-activity) and broadcast on a private channel `exam.{examSessionId}`. The guru's Proctor page subscribes via Laravel Echo (already configured with `@laravel/echo-vue`). Override actions (extend time, terminate, invalidate question) go through a `ProctorService` and broadcast back to affected students. All overrides are logged to `exam_activity_logs`.

**Tech Stack:** Laravel 12 (PHP 8.2+), Laravel Reverb, Laravel Echo, Vue 3 + TypeScript + Inertia.js, shadcn-vue, Pest PHP

---

## File Structure

### New files:
- `app/Events/StudentStartedExam.php` — Broadcast event when student starts exam
- `app/Events/StudentSubmittedExam.php` — Broadcast event when student submits
- `app/Events/AnswerProgressUpdated.php` — Broadcast progress (X/Y answered) on auto-save
- `app/Events/TabSwitchDetected.php` — Broadcast tab switch violation
- `app/Events/ExamTimeExtended.php` — Broadcast to specific student when time is extended
- `app/Events/ExamForceSubmitted.php` — Broadcast to specific student when force-submitted
- `app/Services/Exam/ProctorService.php` — Override actions business logic
- `app/Http/Controllers/Guru/ProctorController.php` — Proctor page + override endpoints
- `resources/js/pages/Guru/Ujian/Proctor.vue` — Proctor dashboard page
- `resources/js/composables/useProctorChannel.ts` — WebSocket subscription composable
- `tests/Feature/ProctorTest.php` — Feature tests

### Modified files:
- `routes/channels.php` — Add exam channel authorization
- `routes/web.php` — Add proctor routes
- `app/Http/Controllers/Siswa/ExamController.php` — Dispatch broadcast events
- `resources/js/types/exam.ts` — Add proctor-related types
- `resources/js/types/index.ts` — Re-export if needed

---

## Chunk 1: Backend Events + Channel Authorization

### Task 1: Broadcasting Events

**Files:**
- Create: `app/Events/StudentStartedExam.php`
- Create: `app/Events/StudentSubmittedExam.php`
- Create: `app/Events/AnswerProgressUpdated.php`
- Create: `app/Events/TabSwitchDetected.php`
- Create: `app/Events/ExamTimeExtended.php`
- Create: `app/Events/ExamForceSubmitted.php`

- [ ] **Step 1: Create StudentStartedExam event**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ExamAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class StudentStartedExam implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->attempt->exam_session_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'user_id' => $this->attempt->user_id,
            'user_name' => $this->attempt->user->name,
            'started_at' => $this->attempt->started_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Create StudentSubmittedExam event**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ExamAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class StudentSubmittedExam implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->attempt->exam_session_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'user_id' => $this->attempt->user_id,
            'user_name' => $this->attempt->user->name,
            'submitted_at' => $this->attempt->submitted_at?->toISOString(),
            'is_force_submitted' => $this->attempt->is_force_submitted,
            'score' => $this->attempt->score,
        ];
    }
}
```

- [ ] **Step 3: Create AnswerProgressUpdated event**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AnswerProgressUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $examSessionId,
        public readonly int $userId,
        public readonly int $answeredCount,
        public readonly int $totalQuestions,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->examSessionId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'answered_count' => $this->answeredCount,
            'total_questions' => $this->totalQuestions,
        ];
    }
}
```

- [ ] **Step 4: Create TabSwitchDetected event**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TabSwitchDetected implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $examSessionId,
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $eventType,
        public readonly int $totalViolations,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->examSessionId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'event_type' => $this->eventType,
            'total_violations' => $this->totalViolations,
        ];
    }
}
```

- [ ] **Step 5: Create ExamTimeExtended event (broadcast to specific student)**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ExamTimeExtended implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $examSessionId,
        public readonly int $userId,
        public readonly int $additionalMinutes,
        public readonly int $newRemainingSeconds,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->examSessionId . '.student.' . $this->userId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'additional_minutes' => $this->additionalMinutes,
            'new_remaining_seconds' => $this->newRemainingSeconds,
        ];
    }
}
```

- [ ] **Step 6: Create ExamForceSubmitted event (broadcast to specific student)**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ExamForceSubmitted implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public readonly int $examSessionId,
        public readonly int $userId,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exam.' . $this->examSessionId . '.student.' . $this->userId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
        ];
    }
}
```

- [ ] **Step 7: Commit events**

```bash
git add app/Events/
git commit -m "[Phase 2] feat: add broadcasting events for proctor dashboard"
```

### Task 2: Channel Authorization + Routes

**Files:**
- Modify: `routes/channels.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add exam channel authorization in channels.php**

Add to `routes/channels.php`:

```php
use App\Enums\UserRole;
use App\Models\ExamSession;

// Exam proctor channel — guru who owns the exam can listen
Broadcast::channel('exam.{examSessionId}', function ($user, int $examSessionId) {
    $examSession = ExamSession::find($examSessionId);
    if (! $examSession) {
        return false;
    }

    // Guru who created the exam can monitor
    return $user->id === $examSession->user_id;
});

// Per-student exam channel — the student themselves can listen
Broadcast::channel('exam.{examSessionId}.student.{userId}', function ($user, int $examSessionId, int $userId) {
    return (int) $user->id === $userId;
});
```

- [ ] **Step 2: Add proctor routes in web.php**

Add inside the guru route group in `routes/web.php`:

```php
use App\Http\Controllers\Guru\ProctorController;

// Proctor Dashboard
Route::get('ujian/{ujian}/proctor', [ProctorController::class, 'show'])->name('ujian.proctor');
Route::post('ujian/{ujian}/proctor/extend-time', [ProctorController::class, 'extendTime'])->name('ujian.proctor.extend-time');
Route::post('ujian/{ujian}/proctor/terminate', [ProctorController::class, 'terminate'])->name('ujian.proctor.terminate');
Route::post('ujian/{ujian}/proctor/invalidate-question', [ProctorController::class, 'invalidateQuestion'])->name('ujian.proctor.invalidate-question');
```

- [ ] **Step 3: Commit**

```bash
git add routes/channels.php routes/web.php
git commit -m "[Phase 2] feat: add proctor channel authorization and routes"
```

---

## Chunk 2: Enum Update + ProctorService + ProctorController + Wire Events

### Task 3: Update ExamActivityEventType Enum

**Files:**
- Modify: `app/Enums/ExamActivityEventType.php`

NOTE: This MUST be done before ProctorService, because ExamActivityLog casts event_type to this enum.

- [ ] **Step 1: Add proctor-specific event types**

Add to the enum:
```php
case ProctorExtendTime = 'proctor_extend_time';
case ProctorTerminate = 'proctor_terminate';
case ProctorInvalidateQuestion = 'proctor_invalidate_question';
```

Add labels:
```php
self::ProctorExtendTime => 'Perpanjangan Waktu (Pengawas)',
self::ProctorTerminate => 'Terminasi (Pengawas)',
self::ProctorInvalidateQuestion => 'Pembatalan Soal (Pengawas)',
```

- [ ] **Step 2: Commit**

```bash
git add app/Enums/ExamActivityEventType.php
git commit -m "[Phase 2] feat: add proctor event types to ExamActivityEventType enum"
```

### Task 4: ProctorService

**Files:**
- Create: `app/Services/Exam/ProctorService.php`

- [ ] **Step 1: Write ProctorService with override methods**

```php
<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\ExamAttemptStatus;
use App\Events\ExamForceSubmitted;
use App\Events\ExamTimeExtended;
use App\Events\StudentSubmittedExam;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Models\User;

class ProctorService
{
    public function __construct(
        private readonly ExamAttemptService $attemptService,
    ) {}

    /**
     * Get proctor dashboard data: all students and their attempt status.
     */
    public function getDashboardData(ExamSession $examSession): array
    {
        $examSession->load([
            'subject',
            'classrooms.students',
            'attempts' => fn ($q) => $q->with(['user', 'activityLogs'])
                ->withCount(['answers as answered_count' => fn ($q) => $q->whereNotNull('answer')]),
        ]);

        // Get total questions for this exam
        $totalQuestions = $examSession->pool_count
            ?? $examSession->questionBank->questions()->count();

        // Build student list from assigned classrooms
        $allStudents = $examSession->classrooms
            ->flatMap(fn ($classroom) => $classroom->students)
            ->unique('id');

        $attemptsByUser = $examSession->attempts->keyBy('user_id');

        $students = $allStudents->map(function (User $student) use ($attemptsByUser, $totalQuestions) {
            $attempt = $attemptsByUser->get($student->id);

            $violationCount = 0;
            if ($attempt) {
                $violationCount = $attempt->activityLogs->count();
            }

            return [
                'id' => $student->id,
                'name' => $student->name,
                'username' => $student->username,
                'attempt_id' => $attempt?->id,
                'status' => $attempt?->status->value ?? 'not_started',
                'status_label' => $attempt?->status->label() ?? 'Belum Mulai',
                'started_at' => $attempt?->started_at?->toISOString(),
                'submitted_at' => $attempt?->submitted_at?->toISOString(),
                'is_force_submitted' => $attempt?->is_force_submitted ?? false,
                'answered_count' => $attempt?->answered_count ?? 0,
                'total_questions' => $totalQuestions,
                'remaining_seconds' => $attempt?->calculateRemainingSeconds() ?? 0,
                'violation_count' => $violationCount,
                'score' => $attempt?->score,
                'ip_address' => $attempt?->ip_address,
            ];
        })->sortBy('name')->values()->toArray();

        // Build questions list for invalidation UI
        $questions = $examSession->questionBank->questions()
            ->select('id', 'order', 'content')
            ->orderBy('order')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'order' => $q->order,
                'content' => strip_tags($q->content),
            ])
            ->toArray();

        return [
            'exam_session' => [
                'id' => $examSession->id,
                'name' => $examSession->name,
                'subject' => $examSession->subject->name,
                'status' => $examSession->status->value,
                'duration_minutes' => $examSession->duration_minutes,
                'starts_at' => $examSession->starts_at->toISOString(),
                'ends_at' => $examSession->ends_at->toISOString(),
                'token' => $examSession->token,
                'total_questions' => $totalQuestions,
                'max_tab_switches' => $examSession->max_tab_switches,
            ],
            'students' => $students,
            'questions' => $questions,
            'summary' => [
                'total' => count($students),
                'not_started' => collect($students)->where('status', 'not_started')->count(),
                'in_progress' => collect($students)->where('status', 'in_progress')->count(),
                'submitted' => collect($students)->where('status', 'submitted')->count(),
                'graded' => collect($students)->where('status', 'graded')->count(),
            ],
        ];
    }

    /**
     * Extend time for a specific student.
     */
    public function extendTime(ExamAttempt $attempt, int $additionalMinutes, User $proctorUser): void
    {
        // Extend by moving started_at back (effectively adding time)
        $attempt->update([
            'started_at' => $attempt->started_at->subMinutes($additionalMinutes),
        ]);

        $newRemaining = $attempt->calculateRemainingSeconds();

        // Log the override
        ExamActivityLog::create([
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'proctor_extend_time',
            'description' => "Guru {$proctorUser->name} menambah waktu {$additionalMinutes} menit",
            'created_at' => now(),
        ]);

        // Broadcast to student
        event(new ExamTimeExtended(
            $attempt->exam_session_id,
            $attempt->user_id,
            $additionalMinutes,
            $newRemaining,
        ));
    }

    /**
     * Terminate/force-submit a student's exam.
     */
    public function terminate(ExamAttempt $attempt, User $proctorUser, string $reason = 'Diterminasi oleh pengawas'): void
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress) {
            return;
        }

        // Log the override before submit
        ExamActivityLog::create([
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'proctor_terminate',
            'description' => "Guru {$proctorUser->name}: {$reason}",
            'created_at' => now(),
        ]);

        $this->attemptService->submitExam($attempt, true);
        $attempt->refresh();

        // Broadcast to proctor channel
        event(new StudentSubmittedExam($attempt));

        // Broadcast to student
        event(new ExamForceSubmitted(
            $attempt->exam_session_id,
            $attempt->user_id,
            $reason,
        ));
    }

    /**
     * Invalidate a question — all students get full points for it.
     */
    public function invalidateQuestion(ExamSession $examSession, int $questionId, User $proctorUser): int
    {
        $question = $examSession->questionBank->questions()->findOrFail($questionId);

        // Update all student_answers for this question in this exam to full points
        $affected = StudentAnswer::whereHas('examAttempt', fn ($q) => $q->where('exam_session_id', $examSession->id))
            ->where('question_id', $questionId)
            ->update([
                'is_correct' => true,
                'score' => $question->points,
                'feedback' => 'Soal dibatalkan oleh pengawas — nilai penuh otomatis.',
            ]);

        // Log for all active attempts
        $activeAttempts = $examSession->attempts()
            ->where('status', ExamAttemptStatus::InProgress)
            ->get();

        foreach ($activeAttempts as $attempt) {
            ExamActivityLog::create([
                'exam_attempt_id' => $attempt->id,
                'event_type' => 'proctor_invalidate_question',
                'description' => "Guru {$proctorUser->name} membatalkan soal #{$question->order}",
                'created_at' => now(),
            ]);
        }

        return $affected;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/Exam/ProctorService.php
git commit -m "[Phase 2] feat: add ProctorService with override actions"
```

### Task 5: ProctorController

**Files:**
- Create: `app/Http/Controllers/Guru/ProctorController.php`

- [ ] **Step 1: Write ProctorController**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Guru/ProctorController.php
git commit -m "[Phase 2] feat: add ProctorController with override endpoints"
```

### Task 6: Wire Broadcasting Events into Student Exam Flow

**Files:**
- Modify: `app/Http/Controllers/Siswa/ExamController.php`

- [ ] **Step 1: Add event dispatching to ExamController::start**

In `app/Http/Controllers/Siswa/ExamController.php`, after creating the attempt in the `start` method (line ~150-158), add event dispatch:

```php
use App\Events\StudentStartedExam;
```

After `$attempt = $this->attemptService->startExam(...)`:
```php
event(new StudentStartedExam($attempt));
```

- [ ] **Step 2: Add event dispatching to ExamController::saveAnswers**

In the `saveAnswers` method, after `$result = $this->attemptService->saveAnswersToRedis(...)`, add:

```php
use App\Events\AnswerProgressUpdated;
```

```php
$answeredCount = count(array_filter($request->input('answers'), fn ($v) => $v !== null && $v !== ''));
$totalQuestions = $attempt->attemptQuestions()->count();

event(new AnswerProgressUpdated(
    $ujian->id,
    $request->user()->id,
    $answeredCount,
    $totalQuestions,
));
```

- [ ] **Step 3: Add event dispatching to ExamController::submit**

In the `submit` method, after `$this->attemptService->submitExam($attempt)`, add:

```php
use App\Events\StudentSubmittedExam;
```

```php
$attempt->refresh();
event(new StudentSubmittedExam($attempt));
```

- [ ] **Step 4: Add event dispatching to ExamController::logActivity for tab switches**

In the `logActivity` method, after `ExamActivityLog::create(...)`, add:

```php
use App\Events\TabSwitchDetected;
```

```php
$attempt->load('examSession');
$totalViolations = ExamActivityLog::where('exam_attempt_id', $attempt->id)->count();

event(new TabSwitchDetected(
    $attempt->examSession->id,
    $request->user()->id,
    $request->user()->name,
    $request->input('event_type'),
    $totalViolations,
));
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Siswa/ExamController.php
git commit -m "[Phase 2] feat: dispatch broadcasting events from student exam flow"
```

---

## Chunk 3: Frontend (Types + Proctor Page + Composable)

### Task 7: TypeScript Types

**Files:**
- Modify: `resources/js/types/exam.ts`

- [ ] **Step 1: Add proctor-related types to exam.ts**

Append to `resources/js/types/exam.ts`:

```typescript
// Proctor Dashboard types
export type ProctorStudentStatus = 'not_started' | 'in_progress' | 'submitted' | 'graded';

export type ProctorStudent = {
    id: number;
    name: string;
    username: string;
    attempt_id: number | null;
    status: ProctorStudentStatus;
    status_label: string;
    started_at: string | null;
    submitted_at: string | null;
    is_force_submitted: boolean;
    answered_count: number;
    total_questions: number;
    remaining_seconds: number;
    violation_count: number;
    score: number | null;
    ip_address: string | null;
};

export type ProctorExamSession = {
    id: number;
    name: string;
    subject: string;
    status: ExamStatus;
    duration_minutes: number;
    starts_at: string;
    ends_at: string;
    token: string;
    total_questions: number;
    max_tab_switches: number | null;
};

export type ProctorSummary = {
    total: number;
    not_started: number;
    in_progress: number;
    submitted: number;
    graded: number;
};

// Broadcasting event payloads
export type StudentStartedEvent = {
    attempt_id: number;
    user_id: number;
    user_name: string;
    started_at: string;
};

export type StudentSubmittedEvent = {
    attempt_id: number;
    user_id: number;
    user_name: string;
    submitted_at: string | null;
    is_force_submitted: boolean;
    score: number | null;
};

export type AnswerProgressEvent = {
    user_id: number;
    answered_count: number;
    total_questions: number;
};

export type TabSwitchEvent = {
    user_id: number;
    user_name: string;
    event_type: string;
    total_violations: number;
};
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/types/exam.ts
git commit -m "[Phase 2] feat: add proctor TypeScript types"
```

### Task 8: Proctor Channel Composable

**Files:**
- Create: `resources/js/composables/useProctorChannel.ts`

- [ ] **Step 1: Create useProctorChannel composable**

```typescript
import { useEcho } from '@laravel/echo-vue';
import { onUnmounted, type Ref } from 'vue';
import type {
    AnswerProgressEvent,
    ProctorStudent,
    StudentStartedEvent,
    StudentSubmittedEvent,
    TabSwitchEvent,
} from '@/types';

export function useProctorChannel(
    examSessionId: number,
    students: Ref<ProctorStudent[]>,
) {
    const echo = useEcho();

    const channel = echo.private(`exam.${examSessionId}`);

    channel.listen('.StudentStartedExam', (event: StudentStartedEvent) => {
        const student = students.value.find((s) => s.id === event.user_id);
        if (student) {
            student.status = 'in_progress';
            student.status_label = 'Sedang Mengerjakan';
            student.attempt_id = event.attempt_id;
            student.started_at = event.started_at;
        }
    });

    channel.listen('.StudentSubmittedExam', (event: StudentSubmittedEvent) => {
        const student = students.value.find((s) => s.id === event.user_id);
        if (student) {
            student.status = 'submitted';
            student.status_label = 'Sudah Dikumpulkan';
            student.submitted_at = event.submitted_at;
            student.is_force_submitted = event.is_force_submitted;
            student.score = event.score;
            student.remaining_seconds = 0;
        }
    });

    channel.listen('.AnswerProgressUpdated', (event: AnswerProgressEvent) => {
        const student = students.value.find((s) => s.id === event.user_id);
        if (student) {
            student.answered_count = event.answered_count;
            student.total_questions = event.total_questions;
        }
    });

    channel.listen('.TabSwitchDetected', (event: TabSwitchEvent) => {
        const student = students.value.find((s) => s.id === event.user_id);
        if (student) {
            student.violation_count = event.total_violations;
        }
    });

    onUnmounted(() => {
        echo.leave(`exam.${examSessionId}`);
    });

    return { channel };
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/composables/useProctorChannel.ts
git commit -m "[Phase 2] feat: add useProctorChannel composable for WebSocket"
```

### Task 9: Proctor Dashboard Vue Page

**Files:**
- Create: `resources/js/pages/Guru/Ujian/Proctor.vue`

- [ ] **Step 1: Create Proctor.vue page**

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Progress } from '@/components/ui/progress';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Copy,
    MonitorCheck,
    Search,
    TimerOff,
    UserCheck,
    UserX,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import { useProctorChannel } from '@/composables/useProctorChannel';
import type {
    BreadcrumbItem,
    ProctorExamSession,
    ProctorStudent,
    ProctorSummary,
} from '@/types';

const props = defineProps<{
    exam_session: ProctorExamSession;
    students: ProctorStudent[];
    summary: ProctorSummary;
    questions: { id: number; order: number; content: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/guru/dashboard' },
    { title: 'Ujian', href: '/guru/ujian' },
    { title: props.exam_session.name, href: `/guru/ujian/${props.exam_session.id}` },
    { title: 'Proctor', href: `/guru/ujian/${props.exam_session.id}/proctor` },
];

// Reactive student list for real-time updates
const studentList = ref<ProctorStudent[]>([...props.students]);
const searchQuery = ref('');
const statusFilter = ref<string>('all');

// Connect WebSocket
useProctorChannel(props.exam_session.id, studentList);

// Update remaining_seconds every second for in-progress students
const timerInterval = setInterval(() => {
    studentList.value.forEach((s) => {
        if (s.status === 'in_progress' && s.remaining_seconds > 0) {
            s.remaining_seconds--;
        }
    });
}, 1000);

onUnmounted(() => clearInterval(timerInterval));

// Computed summary from reactive data
const liveSummary = computed(() => ({
    total: studentList.value.length,
    not_started: studentList.value.filter((s) => s.status === 'not_started').length,
    in_progress: studentList.value.filter((s) => s.status === 'in_progress').length,
    submitted: studentList.value.filter((s) => s.status === 'submitted').length,
    graded: studentList.value.filter((s) => s.status === 'graded').length,
}));

const filteredStudents = computed(() => {
    let result = studentList.value;

    if (statusFilter.value !== 'all') {
        result = result.filter((s) => s.status === statusFilter.value);
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(
            (s) => s.name.toLowerCase().includes(q) || s.username.toLowerCase().includes(q),
        );
    }

    return result;
});

function formatTime(seconds: number): string {
    if (seconds <= 0) return '00:00';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    const map: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        not_started: 'outline',
        in_progress: 'default',
        submitted: 'secondary',
        graded: 'secondary',
    };
    return map[status] ?? 'outline';
}

function copyToken() {
    navigator.clipboard.writeText(props.exam_session.token);
}

// Override dialogs
const showExtendDialog = ref(false);
const showTerminateDialog = ref(false);
const selectedStudent = ref<ProctorStudent | null>(null);

const extendForm = useForm({
    attempt_id: 0,
    additional_minutes: 15,
});

const terminateForm = useForm({
    attempt_id: 0,
    reason: '',
});

function openExtendDialog(student: ProctorStudent) {
    selectedStudent.value = student;
    extendForm.attempt_id = student.attempt_id!;
    extendForm.additional_minutes = 15;
    showExtendDialog.value = true;
}

function submitExtendTime() {
    extendForm.post(`/guru/ujian/${props.exam_session.id}/proctor/extend-time`, {
        preserveScroll: true,
        onSuccess: () => {
            showExtendDialog.value = false;
            // Update local remaining_seconds
            if (selectedStudent.value) {
                selectedStudent.value.remaining_seconds += extendForm.additional_minutes * 60;
            }
        },
    });
}

function openTerminateDialog(student: ProctorStudent) {
    selectedStudent.value = student;
    terminateForm.attempt_id = student.attempt_id!;
    terminateForm.reason = '';
    showTerminateDialog.value = true;
}

function submitTerminate() {
    terminateForm.post(`/guru/ujian/${props.exam_session.id}/proctor/terminate`, {
        preserveScroll: true,
        onSuccess: () => {
            showTerminateDialog.value = false;
        },
    });
}

const showInvalidateDialog = ref(false);
const invalidateForm = useForm({
    question_id: 0,
});

function openInvalidateDialog() {
    invalidateForm.question_id = 0;
    showInvalidateDialog.value = true;
}

function submitInvalidateQuestion() {
    if (!invalidateForm.question_id) return;

    invalidateForm.post(`/guru/ujian/${props.exam_session.id}/proctor/invalidate-question`, {
        preserveScroll: true,
        onSuccess: () => {
            showInvalidateDialog.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Proctor - ${exam_session.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <FlashMessage />

            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <MonitorCheck class="size-5" />
                        <h2 class="text-xl font-semibold">Proctor Dashboard</h2>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ exam_session.name }} &mdash; {{ exam_session.subject }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="openInvalidateDialog">
                        <XCircle class="mr-1 size-4" />
                        Batalkan Soal
                    </Button>
                    <Button variant="outline" size="sm" @click="copyToken">
                        <Copy class="mr-1 size-4" />
                        <code class="font-mono">{{ exam_session.token }}</code>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <a :href="`/guru/ujian/${exam_session.id}`">
                            <ArrowLeft class="mr-1 size-4" />
                            Kembali
                        </a>
                    </Button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Users class="size-4" />
                            Total Peserta
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ liveSummary.total }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Clock class="size-4" />
                            Sedang Mengerjakan
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-blue-600">{{ liveSummary.in_progress }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <UserCheck class="size-4" />
                            Sudah Selesai
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-green-600">{{ liveSummary.submitted + liveSummary.graded }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <UserX class="size-4" />
                            Belum Mulai
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-gray-500">{{ liveSummary.not_started }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Cari siswa..." class="pl-9" />
                </div>
                <div class="flex gap-1">
                    <Button
                        v-for="filter in [
                            { value: 'all', label: 'Semua' },
                            { value: 'not_started', label: 'Belum Mulai' },
                            { value: 'in_progress', label: 'Mengerjakan' },
                            { value: 'submitted', label: 'Selesai' },
                        ]"
                        :key="filter.value"
                        :variant="statusFilter === filter.value ? 'default' : 'outline'"
                        size="sm"
                        @click="statusFilter = filter.value"
                    >
                        {{ filter.label }}
                    </Button>
                </div>
            </div>

            <!-- Student Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[40px]">#</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead class="text-center">Status</TableHead>
                                <TableHead class="text-center">Progress</TableHead>
                                <TableHead class="text-center">Sisa Waktu</TableHead>
                                <TableHead class="text-center">Pelanggaran</TableHead>
                                <TableHead class="text-center">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(student, index) in filteredStudents"
                                :key="student.id"
                                :class="{ 'bg-red-50 dark:bg-red-950/20': student.violation_count > 0 && student.status === 'in_progress' }"
                            >
                                <TableCell class="text-muted-foreground">{{ index + 1 }}</TableCell>
                                <TableCell>
                                    <div>
                                        <p class="font-medium">{{ student.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ student.username }}</p>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="statusVariant(student.status)">
                                        {{ student.status_label }}
                                    </Badge>
                                    <Badge v-if="student.is_force_submitted" variant="destructive" class="ml-1">
                                        Force
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center">
                                    <div v-if="student.status !== 'not_started'" class="flex flex-col items-center gap-1">
                                        <span class="text-sm">{{ student.answered_count }}/{{ student.total_questions }}</span>
                                        <Progress
                                            :model-value="student.total_questions > 0 ? (student.answered_count / student.total_questions) * 100 : 0"
                                            class="h-1.5 w-16"
                                        />
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <span
                                        v-if="student.status === 'in_progress'"
                                        class="font-mono text-sm"
                                        :class="{
                                            'text-red-600 font-bold': student.remaining_seconds <= 60,
                                            'text-orange-500': student.remaining_seconds > 60 && student.remaining_seconds <= 300,
                                        }"
                                    >
                                        {{ formatTime(student.remaining_seconds) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <div v-if="student.violation_count > 0" class="flex items-center justify-center gap-1">
                                        <AlertTriangle class="size-4 text-orange-500" />
                                        <span class="text-sm font-medium text-orange-600">{{ student.violation_count }}</span>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <div v-if="student.status === 'in_progress'" class="flex items-center justify-center gap-1">
                                        <Button variant="outline" size="sm" @click="openExtendDialog(student)">
                                            <Clock class="size-3" />
                                        </Button>
                                        <Button variant="destructive" size="sm" @click="openTerminateDialog(student)">
                                            <TimerOff class="size-3" />
                                        </Button>
                                    </div>
                                    <span v-else-if="student.status === 'submitted' || student.status === 'graded'" class="text-sm">
                                        {{ student.score !== null ? student.score.toFixed(1) : '-' }}
                                    </span>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredStudents.length === 0">
                                <TableCell :colspan="7" class="text-center py-8 text-muted-foreground">
                                    Tidak ada siswa yang cocok dengan filter.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Extend Time Dialog -->
        <Dialog v-model:open="showExtendDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah Waktu</DialogTitle>
                    <DialogDescription>
                        Tambah waktu ujian untuk {{ selectedStudent?.name }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-4 items-center gap-4">
                        <label class="text-right text-sm">Menit</label>
                        <Input
                            v-model.number="extendForm.additional_minutes"
                            type="number"
                            min="1"
                            max="120"
                            class="col-span-3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showExtendDialog = false">Batal</Button>
                    <Button @click="submitExtendTime" :disabled="extendForm.processing">
                        Tambah Waktu
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Terminate Dialog -->
        <AlertDialog v-model:open="showTerminateDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Terminasi Ujian</AlertDialogTitle>
                    <AlertDialogDescription>
                        Apakah Anda yakin ingin menghentikan ujian {{ selectedStudent?.name }}?
                        Jawaban yang sudah disimpan akan dikumpulkan secara otomatis.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div class="py-2">
                    <Input
                        v-model="terminateForm.reason"
                        placeholder="Alasan terminasi (opsional)"
                    />
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="submitTerminate"
                        :disabled="terminateForm.processing"
                    >
                        Terminasi
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Invalidate Question Dialog -->
        <Dialog v-model:open="showInvalidateDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Batalkan Soal</DialogTitle>
                    <DialogDescription>
                        Pilih soal yang akan dibatalkan. Semua siswa akan mendapat nilai penuh untuk soal ini.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="max-h-60 overflow-y-auto space-y-2">
                        <label
                            v-for="q in questions"
                            :key="q.id"
                            class="flex items-start gap-3 p-2 rounded-md hover:bg-muted cursor-pointer"
                            :class="{ 'bg-muted': invalidateForm.question_id === q.id }"
                        >
                            <input
                                type="radio"
                                :value="q.id"
                                v-model="invalidateForm.question_id"
                                class="mt-1"
                            />
                            <div class="text-sm">
                                <span class="font-medium">Soal {{ q.order }}:</span>
                                <span class="text-muted-foreground ml-1" v-html="q.content.substring(0, 100) + (q.content.length > 100 ? '...' : '')"></span>
                            </div>
                        </label>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showInvalidateDialog = false">Batal</Button>
                    <Button variant="destructive" @click="submitInvalidateQuestion" :disabled="invalidateForm.processing || !invalidateForm.question_id">
                        Batalkan Soal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/Guru/Ujian/Proctor.vue
git commit -m "[Phase 2] feat: add Proctor dashboard Vue page with real-time updates"
```

---

## Chunk 4: Feature Tests

### Task 10: Feature Tests

**Files:**
- Create: `tests/Feature/ProctorTest.php`

- [ ] **Step 1: Write ProctorTest.php**

```php
<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Events\ExamForceSubmitted;
use App\Events\ExamTimeExtended;
use App\Events\StudentSubmittedExam;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamActivityLog;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->guru = User::factory()->guru()->create();
    $this->siswa = User::factory()->siswa()->create();
    $this->otherGuru = User::factory()->guru()->create();

    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);

    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);

    $this->classroom->students()->attach($this->siswa->id);

    $this->questionBank = QuestionBank::factory()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->pgQuestion = Question::factory()->pilihanGanda()->create([
        'question_bank_id' => $this->questionBank->id,
        'points' => 2,
        'order' => 1,
    ]);
    QuestionOption::factory()->correct()->create([
        'question_id' => $this->pgQuestion->id,
        'label' => 'A',
        'content' => 'Jawaban benar',
        'order' => 0,
    ]);
    QuestionOption::factory()->create([
        'question_id' => $this->pgQuestion->id,
        'label' => 'B',
        'content' => 'Jawaban salah',
        'order' => 1,
    ]);

    $this->examSession = ExamSession::factory()->active()->create([
        'user_id' => $this->guru->id,
        'subject_id' => $this->subject->id,
        'academic_year_id' => $this->academicYear->id,
        'question_bank_id' => $this->questionBank->id,
        'token' => 'ABCDEF',
        'duration_minutes' => 60,
    ]);

    $this->examSession->classrooms()->attach($this->classroom->id);
});

// ===== Proctor Dashboard Access =====

test('guru can view proctor dashboard for their exam', function () {
    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Guru/Ujian/Proctor')
        ->has('exam_session')
        ->has('students')
        ->has('summary')
    );
});

test('guru cannot view proctor dashboard for other guru exam', function () {
    $response = $this->actingAs($this->otherGuru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertForbidden();
});

test('siswa cannot access proctor dashboard', function () {
    $response = $this->actingAs($this->siswa)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertForbidden();
});

test('proctor dashboard shows all assigned students', function () {
    $siswa2 = User::factory()->siswa()->create();
    $this->classroom->students()->attach($siswa2->id);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->has('students', 2)
        ->where('summary.total', 2)
        ->where('summary.not_started', 2)
    );
});

test('proctor dashboard shows correct status for student with attempt', function () {
    // Create an in-progress attempt
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $this->pgQuestion->id,
        'order' => 1,
    ]);

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $this->pgQuestion->id,
        'answer' => 'A',
        'answered_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->get(route('guru.ujian.proctor', $this->examSession));

    $response->assertInertia(fn ($page) => $page
        ->where('summary.in_progress', 1)
        ->where('summary.not_started', 0)
    );
});

// ===== Extend Time =====

test('guru can extend time for student', function () {
    Event::fake([ExamTimeExtended::class]);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subMinutes(30),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $originalStartedAt = $attempt->started_at->copy();

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 15,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $attempt->refresh();
    expect($attempt->started_at->diffInMinutes($originalStartedAt))->toBe(15);

    // Verify activity log
    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'proctor_extend_time',
    ]);

    Event::assertDispatched(ExamTimeExtended::class, function ($event) use ($attempt) {
        return $event->userId === $attempt->user_id
            && $event->additionalMinutes === 15;
    });
});

test('guru cannot extend time for submitted attempt', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subMinutes(30),
        'status' => ExamAttemptStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 15,
        ]);

    $response->assertNotFound();
});

test('other guru cannot extend time on exam they do not own', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->otherGuru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 15,
        ]);

    $response->assertForbidden();
});

// ===== Terminate =====

test('guru can terminate student exam', function () {
    Event::fake([StudentSubmittedExam::class, ExamForceSubmitted::class]);
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('del')->andReturn(1);

    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.terminate', $this->examSession), [
            'attempt_id' => $attempt->id,
            'reason' => 'Kecurangan terdeteksi',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect($attempt->is_force_submitted)->toBeTrue();

    // Verify activity log
    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'proctor_terminate',
    ]);

    Event::assertDispatched(StudentSubmittedExam::class);
    Event::assertDispatched(ExamForceSubmitted::class, function ($event) {
        return $event->reason === 'Kecurangan terdeteksi';
    });
});

test('guru cannot terminate already submitted exam', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now()->subMinutes(30),
        'status' => ExamAttemptStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.terminate', $this->examSession), [
            'attempt_id' => $attempt->id,
        ]);

    $response->assertNotFound();
});

// ===== Invalidate Question =====

test('guru can invalidate a question', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    StudentAnswer::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $this->pgQuestion->id,
        'answer' => 'B',
        'is_correct' => false,
        'score' => 0,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.invalidate-question', $this->examSession), [
            'question_id' => $this->pgQuestion->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify answer was updated to full points
    $answer = StudentAnswer::where('exam_attempt_id', $attempt->id)
        ->where('question_id', $this->pgQuestion->id)
        ->first();

    expect($answer->is_correct)->toBeTrue();
    expect((float) $answer->score)->toBe(2.0);

    // Verify activity log
    $this->assertDatabaseHas('exam_activity_logs', [
        'exam_attempt_id' => $attempt->id,
        'event_type' => 'proctor_invalidate_question',
    ]);
});

// ===== Validation =====

test('extend time requires valid minutes', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 0,
        ]);

    $response->assertSessionHasErrors('additional_minutes');
});

test('extend time rejects minutes over 120', function () {
    $attempt = ExamAttempt::factory()->create([
        'exam_session_id' => $this->examSession->id,
        'user_id' => $this->siswa->id,
        'started_at' => now(),
        'status' => ExamAttemptStatus::InProgress,
    ]);

    $response = $this->actingAs($this->guru)
        ->post(route('guru.ujian.proctor.extend-time', $this->examSession), [
            'attempt_id' => $attempt->id,
            'additional_minutes' => 200,
        ]);

    $response->assertSessionHasErrors('additional_minutes');
});
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=ProctorTest
```

Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ProctorTest.php
git commit -m "[Phase 2] test: add feature tests for proctor dashboard"
```

---

## Chunk 5: Add Proctor Link to Existing UI

### Task 11: Add Proctor Button to Exam Show Page

**Files:**
- Modify: `resources/js/pages/Guru/Ujian/Show.vue`

- [ ] **Step 1: Add Proctor button to Show.vue header**

In `resources/js/pages/Guru/Ujian/Show.vue`, add a "Proctor" button in the header actions area next to the Edit button, only visible when exam is active:

```vue
<Button v-if="examSession.status === 'active'" variant="default" size="sm" as-child>
    <a :href="`/guru/ujian/${examSession.id}/proctor`">
        <MonitorCheck class="size-4" />
        Proctor
    </a>
</Button>
```

Import `MonitorCheck` from `lucide-vue-next`.

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/Guru/Ujian/Show.vue
git commit -m "[Phase 2] feat: add proctor button to exam show page"
```

### Task 12: Ensure ExamAttempt Factory Exists

**Files:**
- Check/Create: `database/factories/ExamAttemptFactory.php`

NOTE: This factory likely already exists in the codebase. Only create it if missing.

- [ ] **Step 1: Verify ExamAttemptFactory exists and has needed states**

The factory should support creating attempts with in_progress status. If it doesn't exist, create it:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        return [
            'exam_session_id' => ExamSession::factory(),
            'user_id' => User::factory(),
            'started_at' => now(),
            'status' => ExamAttemptStatus::InProgress,
        ];
    }

    public function submitted(): static
    {
        return $this->state([
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Commit if factory was created/modified**

```bash
git add database/factories/ExamAttemptFactory.php
git commit -m "[Phase 2] chore: add ExamAttemptFactory"
```
