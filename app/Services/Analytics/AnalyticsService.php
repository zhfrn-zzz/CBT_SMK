<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get monthly average score trends per subject for a classroom.
     */
    public function getClassScoreTrend(Classroom $classroom, AcademicYear $academicYear): array
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? DB::raw("CAST(strftime('%m', exam_attempts.submitted_at) AS INTEGER) as month")
            : DB::raw('MONTH(exam_attempts.submitted_at) as month');

        $results = ExamAttempt::query()
            ->join('exam_sessions', 'exam_attempts.exam_session_id', '=', 'exam_sessions.id')
            ->join('exam_session_classroom', 'exam_sessions.id', '=', 'exam_session_classroom.exam_session_id')
            ->join('subjects', 'exam_sessions.subject_id', '=', 'subjects.id')
            ->where('exam_session_classroom.classroom_id', $classroom->id)
            ->where('exam_sessions.academic_year_id', $academicYear->id)
            ->where('exam_sessions.is_results_published', true)
            ->whereNotNull('exam_attempts.score')
            ->whereIn('exam_attempts.status', ['submitted', 'graded'])
            ->select([
                $monthExpr,
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                DB::raw('AVG(exam_attempts.score) as avg_score'),
            ])
            ->groupBy('month', 'subjects.id', 'subjects.name')
            ->orderBy('month')
            ->get();

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return $results->map(fn ($row) => [
            'month' => (int) $row->month,
            'month_label' => $months[$row->month - 1] ?? '',
            'avg_score' => round((float) $row->avg_score, 2),
            'subject_id' => $row->subject_id,
            'subject_name' => $row->subject_name,
        ])->toArray();
    }

    /**
     * Get per-classroom average scores grouped by subject for an academic year.
     */
    public function getClassroomComparison(AcademicYear $academicYear, ?Department $department = null): array
    {
        $query = ExamAttempt::query()
            ->join('exam_sessions', 'exam_attempts.exam_session_id', '=', 'exam_sessions.id')
            ->join('exam_session_classroom', 'exam_sessions.id', '=', 'exam_session_classroom.exam_session_id')
            ->join('classrooms', 'exam_session_classroom.classroom_id', '=', 'classrooms.id')
            ->join('subjects', 'exam_sessions.subject_id', '=', 'subjects.id')
            ->where('exam_sessions.academic_year_id', $academicYear->id)
            ->where('exam_sessions.is_results_published', true)
            ->whereNotNull('exam_attempts.score')
            ->whereIn('exam_attempts.status', ['submitted', 'graded']);

        if ($department) {
            $query->where('classrooms.department_id', $department->id);
        }

        return $query->select([
            'classrooms.id as classroom_id',
            'classrooms.name as classroom_name',
            'subjects.id as subject_id',
            'subjects.name as subject_name',
            DB::raw('AVG(exam_attempts.score) as avg_score'),
            DB::raw('COUNT(DISTINCT exam_attempts.user_id) as student_count'),
        ])
            ->groupBy('classrooms.id', 'classrooms.name', 'subjects.id', 'subjects.name')
            ->get()
            ->map(fn ($row) => [
                'classroom_id' => $row->classroom_id,
                'classroom_name' => $row->classroom_name,
                'subject_id' => $row->subject_id,
                'subject_name' => $row->subject_name,
                'avg_score' => round((float) $row->avg_score, 2),
                'student_count' => $row->student_count,
            ])->toArray();
    }

    /**
     * Get overall stats per classroom for the rekap table.
     */
    public function getClassroomStats(AcademicYear $academicYear, ?Department $department = null): array
    {
        $query = ExamAttempt::query()
            ->join('exam_sessions', 'exam_attempts.exam_session_id', '=', 'exam_sessions.id')
            ->join('exam_session_classroom', 'exam_sessions.id', '=', 'exam_session_classroom.exam_session_id')
            ->join('classrooms', 'exam_session_classroom.classroom_id', '=', 'classrooms.id')
            ->where('exam_sessions.academic_year_id', $academicYear->id)
            ->where('exam_sessions.is_results_published', true)
            ->whereNotNull('exam_attempts.score')
            ->whereIn('exam_attempts.status', ['submitted', 'graded']);

        if ($department) {
            $query->where('classrooms.department_id', $department->id);
        }

        return $query->select([
            'classrooms.id as classroom_id',
            'classrooms.name as classroom_name',
            DB::raw('AVG(exam_attempts.score) as avg_score'),
            DB::raw('MAX(exam_attempts.score) as max_score'),
            DB::raw('MIN(exam_attempts.score) as min_score'),
            DB::raw('COUNT(DISTINCT exam_attempts.user_id) as student_count'),
            DB::raw('COUNT(DISTINCT exam_sessions.id) as exam_count'),
        ])
            ->groupBy('classrooms.id', 'classrooms.name')
            ->get()
            ->map(fn ($row) => [
                'classroom_id' => $row->classroom_id,
                'classroom_name' => $row->classroom_name,
                'avg_score' => round((float) $row->avg_score, 2),
                'max_score' => round((float) $row->max_score, 2),
                'min_score' => round((float) $row->min_score, 2),
                'student_count' => (int) $row->student_count,
                'exam_count' => (int) $row->exam_count,
            ])->toArray();
    }
}
