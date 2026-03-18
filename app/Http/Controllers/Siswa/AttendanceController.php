<?php

declare(strict_types=1);

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siswa\CheckInAttendanceRequest;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Services\LMS\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        $classroomIds = $student->classrooms()->pluck('classrooms.id');
        $subjectId = $request->integer('subject_id') ?: null;

        $query = AttendanceRecord::with(['attendance.subject', 'attendance.classroom'])
            ->where('user_id', $student->id)
            ->whereHas('attendance', fn ($q) => $q->whereIn('classroom_id', $classroomIds))
            ->orderByDesc('created_at');

        if ($subjectId) {
            $query->whereHas('attendance', fn ($q) => $q->where('subject_id', $subjectId));
        }

        $records = $query->paginate(20)->withQueryString();

        // Summary per subject/classroom
        $summary = AttendanceRecord::where('user_id', $student->id)
            ->whereHas('attendance', fn ($q) => $q->whereIn('classroom_id', $classroomIds))
            ->with('attendance')
            ->get()
            ->groupBy(fn ($r) => $r->attendance->subject_id)
            ->map(function ($group) {
                $total = $group->count();

                return [
                    'total' => $total,
                    'hadir' => $group->where('status.value', 'hadir')->count(),
                    'izin' => $group->where('status.value', 'izin')->count(),
                    'sakit' => $group->where('status.value', 'sakit')->count(),
                    'alfa' => $group->where('status.value', 'alfa')->count(),
                    'percentage' => $total > 0
                        ? round($group->where('status.value', 'hadir')->count() / $total * 100, 1)
                        : 0,
                ];
            });

        return Inertia::render('Siswa/Presensi/Index', [
            'records' => $records,
            'summary' => $summary,
            'filters' => ['subject_id' => $subjectId],
        ]);
    }

    public function checkIn(CheckInAttendanceRequest $request): RedirectResponse
    {
        $student = $request->user();
        $code = $request->input('code');

        // Find open session matching the code
        $attendance = Attendance::where('access_code', $code)
            ->where('is_open', true)
            ->first();

        if (! $attendance) {
            return back()->withErrors(['code' => 'Kode presensi salah atau sesi sudah ditutup.']);
        }

        try {
            $this->service->checkIn($attendance, $student, $code);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('success', 'Presensi berhasil dicatat.');
    }
}
