<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\ExamSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CalendarEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
        ]);

        $user = $request->user();
        $month = (int) $request->input('month');
        $year = (int) $request->input('year');

        $cacheKey = "calendar:{$user->id}:{$year}:{$month}";

        $events = Cache::remember($cacheKey, 600, function () use ($user, $month, $year) {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
            $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

            $events = [];

            if ($user->isSiswa()) {
                $classroomIds = $user->classrooms()->pluck('classrooms.id')->toArray();
                $events = array_merge(
                    $this->getSiswaExams($classroomIds, $startOfMonth, $endOfMonth),
                    $this->getSiswaAssignments($classroomIds, $startOfMonth, $endOfMonth),
                    $this->getSiswaAttendances($classroomIds, $startOfMonth, $endOfMonth),
                );
            } elseif ($user->isGuru()) {
                $events = array_merge(
                    $this->getGuruExams($user->id, $startOfMonth, $endOfMonth),
                    $this->getGuruAssignments($user->id, $startOfMonth, $endOfMonth),
                    $this->getGuruAttendances($user->id, $startOfMonth, $endOfMonth),
                );
            }

            return $events;
        });

        return response()->json($events);
    }

    private function getSiswaExams(array $classroomIds, Carbon $start, Carbon $end): array
    {
        if (empty($classroomIds)) {
            return [];
        }

        return ExamSession::whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))
            ->where('is_published', true)
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<=', $end)
            ->with('subject:id,name')
            ->get()
            ->map(fn ($exam) => [
                'id' => $exam->id,
                'title' => $exam->name,
                'date' => $exam->starts_at->toDateString(),
                'type' => 'exam',
                'subject' => $exam->subject?->name,
            ])
            ->toArray();
    }

    private function getSiswaAssignments(array $classroomIds, Carbon $start, Carbon $end): array
    {
        if (empty($classroomIds)) {
            return [];
        }

        return Assignment::whereIn('classroom_id', $classroomIds)
            ->where('is_published', true)
            ->where('deadline_at', '>=', $start)
            ->where('deadline_at', '<=', $end)
            ->with('subject:id,name')
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'date' => $assignment->deadline_at->toDateString(),
                'type' => 'assignment',
                'subject' => $assignment->subject?->name,
            ])
            ->toArray();
    }

    private function getSiswaAttendances(array $classroomIds, Carbon $start, Carbon $end): array
    {
        if (empty($classroomIds)) {
            return [];
        }

        return Attendance::whereIn('classroom_id', $classroomIds)
            ->where('meeting_date', '>=', $start->toDateString())
            ->where('meeting_date', '<=', $end->toDateString())
            ->with('subject:id,name')
            ->get()
            ->map(fn ($att) => [
                'id' => $att->id,
                'title' => 'Presensi: ' . ($att->subject?->name ?? 'Unknown'),
                'date' => $att->meeting_date->toDateString(),
                'type' => 'attendance',
                'subject' => $att->subject?->name,
            ])
            ->toArray();
    }

    private function getGuruExams(int $userId, Carbon $start, Carbon $end): array
    {
        return ExamSession::where('user_id', $userId)
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<=', $end)
            ->with('subject:id,name')
            ->get()
            ->map(fn ($exam) => [
                'id' => $exam->id,
                'title' => $exam->name,
                'date' => $exam->starts_at->toDateString(),
                'type' => 'exam',
                'subject' => $exam->subject?->name,
            ])
            ->toArray();
    }

    private function getGuruAssignments(int $userId, Carbon $start, Carbon $end): array
    {
        return Assignment::where('user_id', $userId)
            ->where('deadline_at', '>=', $start)
            ->where('deadline_at', '<=', $end)
            ->with('subject:id,name')
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'date' => $assignment->deadline_at->toDateString(),
                'type' => 'assignment',
                'subject' => $assignment->subject?->name,
            ])
            ->toArray();
    }

    private function getGuruAttendances(int $userId, Carbon $start, Carbon $end): array
    {
        return Attendance::where('user_id', $userId)
            ->where('meeting_date', '>=', $start->toDateString())
            ->where('meeting_date', '<=', $end->toDateString())
            ->with('subject:id,name')
            ->get()
            ->map(fn ($att) => [
                'id' => $att->id,
                'title' => 'Presensi: ' . ($att->subject?->name ?? 'Unknown'),
                'date' => $att->meeting_date->toDateString(),
                'type' => 'attendance',
                'subject' => $att->subject?->name,
            ])
            ->toArray();
    }
}
