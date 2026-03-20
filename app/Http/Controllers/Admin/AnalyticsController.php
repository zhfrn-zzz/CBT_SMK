<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function index(Request $request): Response
    {
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $selectedYearId = $request->integer('academic_year_id') ?: ($academicYears->first()?->id);
        $selectedDepartmentId = $request->integer('department_id') ?: null;

        $academicYear = $selectedYearId ? AcademicYear::find($selectedYearId) : null;
        $department = $selectedDepartmentId ? Department::find($selectedDepartmentId) : null;
        $departments = Department::orderBy('name')->get();

        $classroomStats = [];
        $classroomComparison = [];

        if ($academicYear) {
            $classroomStats = $this->analyticsService->getClassroomStats($academicYear, $department);
            $classroomComparison = $this->analyticsService->getClassroomComparison($academicYear, $department);
        }

        return Inertia::render('Admin/Analytics/Index', [
            'academicYears' => $academicYears,
            'departments' => $departments,
            'classroomStats' => $classroomStats,
            'classroomComparison' => $classroomComparison,
            'filters' => [
                'academic_year_id' => $selectedYearId,
                'department_id' => $selectedDepartmentId,
            ],
        ]);
    }

    public function classroomDetail(Request $request, Classroom $classroom): Response
    {
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $selectedYearId = $request->integer('academic_year_id') ?: ($academicYears->first()?->id);
        $academicYear = $selectedYearId ? AcademicYear::find($selectedYearId) : null;

        $trend = [];
        if ($academicYear) {
            $trend = $this->analyticsService->getClassScoreTrend($classroom, $academicYear);
        }

        return Inertia::render('Admin/Analytics/ClassroomDetail', [
            'classroom' => $classroom->load('department'),
            'academicYears' => $academicYears,
            'trend' => $trend,
            'filters' => ['academic_year_id' => $selectedYearId],
        ]);
    }
}
