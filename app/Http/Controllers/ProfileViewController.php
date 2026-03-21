<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProfileViewController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();

        $this->authorizeView($viewer, $user);

        $profileData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'photo_url' => $user->photo_url,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'created_at' => $user->created_at->toISOString(),
            ],
        ];

        if ($user->role === UserRole::Siswa) {
            $profileData['siswa'] = $this->getSiswaProfile($user);
        } elseif ($user->role === UserRole::Guru) {
            $profileData['guru'] = $this->getGuruProfile($user);
        }

        return Inertia::render('Profile/Show', $profileData);
    }

    private function authorizeView(User $viewer, User $target): void
    {
        if ($viewer->id === $target->id) {
            return;
        }

        if ($viewer->role === UserRole::Admin) {
            return;
        }

        if ($viewer->role === UserRole::Guru) {
            $guruClassroomIds = $viewer->teachingClassrooms()->pluck('classrooms.id');
            $targetClassroomIds = $target->classrooms()->pluck('classrooms.id');
            if ($guruClassroomIds->intersect($targetClassroomIds)->isNotEmpty()) {
                return;
            }
        }

        if ($viewer->role === UserRole::Siswa) {
            if ($target->role === UserRole::Guru) {
                $studentClassroomIds = $viewer->classrooms()->pluck('classrooms.id');
                $guruClassroomIds = $target->teachingClassrooms()->pluck('classrooms.id');
                if ($studentClassroomIds->intersect($guruClassroomIds)->isNotEmpty()) {
                    return;
                }
            }
            if ($target->role === UserRole::Siswa) {
                $viewerClassrooms = $viewer->classrooms()->pluck('classrooms.id');
                $targetClassrooms = $target->classrooms()->pluck('classrooms.id');
                if ($viewerClassrooms->intersect($targetClassrooms)->isNotEmpty()) {
                    return;
                }
            }
        }

        abort(HttpResponse::HTTP_FORBIDDEN, 'Anda tidak memiliki akses untuk melihat profil ini.');
    }

    private function getSiswaProfile(User $user): array
    {
        $classrooms = $user->classrooms()
            ->with('department')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'department' => $c->department?->name,
            ]);

        $examResults = ExamAttempt::where('user_id', $user->id)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Graded])
            ->whereHas('examSession', fn ($q) => $q->where('is_results_published', true))
            ->with(['examSession.subject'])
            ->latest('submitted_at')
            ->take(20)
            ->get()
            ->map(function (ExamAttempt $attempt) {
                $session = $attempt->examSession;
                $kkm = (float) ($session->kkm ?? 0);

                return [
                    'exam_name' => $session->name,
                    'subject' => $session->subject->name,
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'kkm' => $kkm,
                    'pass_status' => $kkm > 0 && $attempt->score !== null
                        ? ((float) $attempt->score >= $kkm ? 'lulus' : 'remedial')
                        : null,
                    'submitted_at' => $attempt->submitted_at?->toISOString(),
                ];
            });

        $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
            ->with('attendance')
            ->get();

        $totalMeetings = $attendanceRecords->count();
        $attendanceSummary = [
            'total' => $totalMeetings,
            'hadir' => $attendanceRecords->where('status.value', 'hadir')->count(),
            'izin' => $attendanceRecords->where('status.value', 'izin')->count(),
            'sakit' => $attendanceRecords->where('status.value', 'sakit')->count(),
            'alfa' => $attendanceRecords->where('status.value', 'alfa')->count(),
            'percentage' => $totalMeetings > 0
                ? round($attendanceRecords->where('status.value', 'hadir')->count() / $totalMeetings * 100, 1)
                : 0,
        ];

        return [
            'classrooms' => $classrooms,
            'exam_results' => $examResults,
            'attendance' => $attendanceSummary,
        ];
    }

    private function getGuruProfile(User $user): array
    {
        $subjects = $user->teachingSubjects()
            ->distinct()
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);

        $classrooms = $user->teachingClassrooms()
            ->distinct()
            ->with('department')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'department' => $c->department?->name,
            ]);

        return [
            'subjects' => $subjects,
            'classrooms' => $classrooms,
        ];
    }
}
