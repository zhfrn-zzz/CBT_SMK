<?php

declare(strict_types=1);

namespace App\Services\LMS;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AttendanceService
{
    public function getSessions(int $classroomId, int $subjectId): LengthAwarePaginator
    {
        return Attendance::with(['classroom', 'subject'])
            ->forClassroom($classroomId, $subjectId)
            ->withCount([
                'records as present_count' => fn ($q) => $q->where('status', AttendanceStatus::Hadir),
                'records as total_records',
            ])
            ->orderByDesc('meeting_date')
            ->paginate(20);
    }

    public function openSession(array $data): Attendance
    {
        $durationMinutes = $data['duration_minutes'] ?? 30;

        $attendance = Attendance::create([
            'classroom_id' => $data['classroom_id'],
            'subject_id' => $data['subject_id'],
            'user_id' => $data['user_id'],
            'meeting_date' => $data['meeting_date'],
            'meeting_number' => $data['meeting_number'],
            'access_code' => $this->generateAccessCode(),
            'code_expires_at' => $durationMinutes > 0 ? now()->addMinutes($durationMinutes) : null,
            'is_open' => true,
            'note' => $data['note'] ?? null,
        ]);

        return $attendance;
    }

    public function closeSession(Attendance $attendance): void
    {
        // Only auto-mark Alfa if code had an expiration time (finite session)
        // If code_expires_at is NULL, the session had unlimited time — don't penalize
        if ($attendance->code_expires_at !== null) {
            $studentIds = $attendance->classroom->students()->pluck('users.id');
            $recordedIds = $attendance->records()->pluck('user_id');
            $unrecordedIds = $studentIds->diff($recordedIds);

            foreach ($unrecordedIds as $studentId) {
                AttendanceRecord::create([
                    'attendance_id' => $attendance->id,
                    'user_id' => $studentId,
                    'status' => AttendanceStatus::Alfa,
                ]);
            }
        }

        $attendance->update(['is_open' => false]);
    }

    public function regenerateCode(Attendance $attendance, int $durationMinutes = 30): string
    {
        $code = $this->generateAccessCode();
        $attendance->update([
            'access_code' => $code,
            'code_expires_at' => $durationMinutes > 0 ? now()->addMinutes($durationMinutes) : null,
        ]);

        return $code;
    }

    public function generateAccessCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function checkIn(Attendance $attendance, User $student, string $code): AttendanceRecord
    {
        if (! $attendance->is_open) {
            throw new \RuntimeException('Sesi presensi sudah ditutup.');
        }

        if ($attendance->is_code_expired) {
            throw new \RuntimeException('Kode sudah kedaluwarsa, minta guru untuk generate kode baru.');
        }

        if ($attendance->access_code !== $code) {
            throw new \RuntimeException('Kode presensi salah.');
        }

        $inClassroom = $student->classrooms()->where('classrooms.id', $attendance->classroom_id)->exists();
        if (! $inClassroom) {
            throw new \RuntimeException('Anda tidak terdaftar di kelas ini.');
        }

        $existing = AttendanceRecord::where('attendance_id', $attendance->id)
            ->where('user_id', $student->id)
            ->first();

        if ($existing) {
            throw new \RuntimeException('Anda sudah melakukan presensi.');
        }

        return AttendanceRecord::create([
            'attendance_id' => $attendance->id,
            'user_id' => $student->id,
            'status' => AttendanceStatus::Hadir,
            'checked_in_at' => now(),
        ]);
    }

    public function setStatus(
        Attendance $attendance,
        int $studentId,
        AttendanceStatus $status,
        ?string $note = null
    ): AttendanceRecord {
        return AttendanceRecord::updateOrCreate(
            ['attendance_id' => $attendance->id, 'user_id' => $studentId],
            ['status' => $status, 'note' => $note]
        );
    }

    public function bulkSetStatus(Attendance $attendance, array $records): void
    {
        foreach ($records as $record) {
            $this->setStatus(
                $attendance,
                $record['user_id'],
                AttendanceStatus::from($record['status']),
                $record['note'] ?? null
            );
        }
    }

    public function getRecap(
        int $classroomId,
        int $subjectId,
        ?string $startDate = null,
        ?string $endDate = null
    ): Collection {
        $query = Attendance::forClassroom($classroomId, $subjectId);

        if ($startDate) {
            $query->where('meeting_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('meeting_date', '<=', $endDate);
        }

        $attendanceIds = $query->pluck('id');
        $totalMeetings = $attendanceIds->count();

        $students = \App\Models\Classroom::find($classroomId)->students()->orderBy('name')->get();

        // Batch-load all records for these attendances to avoid N+1
        $allRecords = AttendanceRecord::whereIn('attendance_id', $attendanceIds)
            ->get()
            ->groupBy('user_id');

        return $students->map(function ($student) use ($allRecords, $totalMeetings) {
            $records = $allRecords->get($student->id, collect());

            $hadir = $records->where('status', AttendanceStatus::Hadir)->count();
            $izin = $records->where('status', AttendanceStatus::Izin)->count();
            $sakit = $records->where('status', AttendanceStatus::Sakit)->count();
            $alfa = $records->where('status', AttendanceStatus::Alfa)->count();

            return [
                'user' => $student,
                'total_meetings' => $totalMeetings,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alfa' => $alfa,
                'percentage' => $totalMeetings > 0 ? round($hadir / $totalMeetings * 100, 1) : 0,
            ];
        });
    }

    public function getNextMeetingNumber(int $classroomId, int $subjectId): int
    {
        $last = Attendance::forClassroom($classroomId, $subjectId)->max('meeting_number');

        return ($last ?? 0) + 1;
    }
}
