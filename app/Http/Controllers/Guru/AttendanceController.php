<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\TeachingAssignment;
use App\Services\LMS\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;

        $sessions = null;
        if ($subjectId && $classroomId) {
            $sessions = $this->service->getSessions($classroomId, $subjectId)->withQueryString();
        }

        return Inertia::render('Guru/Presensi/Index', [
            'sessions' => $sessions,
            'teachingAssignments' => $assignments,
            'filters' => ['subject_id' => $subjectId, 'classroom_id' => $classroomId],
        ]);
    }

    public function create(Request $request): Response
    {
        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $request->user()->id)
            ->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;

        $nextMeetingNumber = 1;
        if ($subjectId && $classroomId) {
            $nextMeetingNumber = $this->service->getNextMeetingNumber($classroomId, $subjectId);
        }

        return Inertia::render('Guru/Presensi/Create', [
            'teachingAssignments' => $assignments,
            'nextMeetingNumber' => $nextMeetingNumber,
            'filters' => ['subject_id' => $subjectId, 'classroom_id' => $classroomId],
        ]);
    }

    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        try {
            $attendance = $this->service->openSession($data);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return back()->withErrors(['meeting_date' => 'Sesi presensi untuk kelas ini hari ini sudah ada.']);
        }

        return redirect()->route('guru.presensi.show', $attendance)
            ->with('success', 'Sesi presensi berhasil dibuka.');
    }

    public function show(Attendance $attendance, Request $request): Response
    {
        $this->authorize('update', $attendance);

        $attendance->load(['classroom', 'subject']);

        $students = $attendance->classroom->students()->orderBy('name')->get();
        $records = $attendance->records()->with('user')->get()->keyBy('user_id');

        return Inertia::render('Guru/Presensi/Show', [
            'attendance' => $attendance,
            'students' => $students,
            'records' => $records,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function close(Attendance $attendance): RedirectResponse
    {
        $this->authorize('close', $attendance);

        $this->service->closeSession($attendance);

        return back()->with('success', 'Sesi presensi ditutup. Siswa yang belum presensi ditandai Alfa.');
    }

    public function regenerateCode(Attendance $attendance, Request $request): RedirectResponse
    {
        $this->authorize('update', $attendance);

        $request->validate(['duration_minutes' => ['required', 'integer', 'min:0']]);

        $newCode = $this->service->regenerateCode($attendance, $request->integer('duration_minutes'));

        return back()->with('success', "Kode baru: {$newCode}");
    }

    public function updateStatus(Attendance $attendance, Request $request): RedirectResponse
    {
        $this->authorize('update', $attendance);

        $request->validate([
            'records' => ['required', 'array'],
            'records.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'records.*.status' => ['required', 'in:hadir,izin,sakit,alfa'],
            'records.*.note' => ['nullable', 'string'],
        ]);

        $this->service->bulkSetStatus($attendance, $request->input('records'));

        return back()->with('success', 'Status presensi berhasil diperbarui.');
    }

    public function recap(Request $request): Response
    {
        $user = $request->user();

        $assignments = TeachingAssignment::with(['classroom', 'subject'])
            ->where('user_id', $user->id)
            ->get();

        $subjectId = $request->integer('subject_id') ?: null;
        $classroomId = $request->integer('classroom_id') ?: null;
        $startDate = $request->string('start_date')->value() ?: null;
        $endDate = $request->string('end_date')->value() ?: null;

        $recapData = null;
        if ($subjectId && $classroomId) {
            $recapData = $this->service->getRecap($classroomId, $subjectId, $startDate, $endDate);
        }

        return Inertia::render('Guru/Presensi/Recap', [
            'recap' => $recapData,
            'teachingAssignments' => $assignments,
            'filters' => [
                'subject_id' => $subjectId,
                'classroom_id' => $classroomId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function exportRecap(Request $request): mixed
    {
        $subjectId = $request->integer('subject_id');
        $classroomId = $request->integer('classroom_id');

        if (! $subjectId || ! $classroomId) {
            abort(400, 'Subject dan Classroom harus dipilih.');
        }

        $recap = $this->service->getRecap($classroomId, $subjectId);
        $filename = "rekap_presensi_{$classroomId}_{$subjectId}_".now()->format('Y_m_d').'.xlsx';

        return Excel::download(new \App\Exports\AttendanceRecapExport($recap), $filename);
    }
}
