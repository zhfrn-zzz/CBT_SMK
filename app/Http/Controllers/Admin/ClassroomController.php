<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): Response
    {
        $classrooms = Classroom::with(['academicYear', 'department'])
            ->withCount('students')
            ->when($request->input('academic_year_id'), fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->input('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Classrooms/Index', [
            'classrooms' => $classrooms,
            'filters' => $request->only(['academic_year_id', 'department_id']),
            'academicYears' => AcademicYear::select('id', 'name', 'semester')->latest()->get(),
            'departments' => Department::select('id', 'name', 'code')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Classrooms/Create', [
            'academicYears' => AcademicYear::select('id', 'name', 'semester')->latest()->get(),
            'departments' => Department::select('id', 'name', 'code')->orderBy('name')->get(),
            'gradeLevels' => collect(GradeLevel::cases())->map(fn (GradeLevel $g) => [
                'value' => $g->value,
                'label' => $g->label(),
            ]),
        ]);
    }

    public function store(ClassroomRequest $request): RedirectResponse
    {
        Classroom::create($request->validated());

        return redirect()->route('admin.classrooms.index')
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function edit(Classroom $classroom): Response
    {
        return Inertia::render('Admin/Classrooms/Edit', [
            'classroom' => $classroom,
            'academicYears' => AcademicYear::select('id', 'name', 'semester')->latest()->get(),
            'departments' => Department::select('id', 'name', 'code')->orderBy('name')->get(),
            'gradeLevels' => collect(GradeLevel::cases())->map(fn (GradeLevel $g) => [
                'value' => $g->value,
                'label' => $g->label(),
            ]),
        ]);
    }

    public function update(ClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $classroom->update($request->validated());

        return redirect()->route('admin.classrooms.index')
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $classroom->delete();

        return redirect()->route('admin.classrooms.index')
            ->with('success', 'Kelas berhasil dihapus.');
    }

    /**
     * Show classroom detail with student list + assignment forms.
     */
    public function show(Classroom $classroom): Response
    {
        $classroom->load(['academicYear', 'department', 'students']);

        // Get students already in this classroom
        $assignedStudentIds = $classroom->students->pluck('id');

        // Available students (siswa role, not already in this classroom)
        $availableStudents = User::where('role', UserRole::Siswa)
            ->where('is_active', true)
            ->whereNotIn('id', $assignedStudentIds)
            ->select('id', 'name', 'username')
            ->orderBy('name')
            ->get();

        // Teaching assignments for this classroom
        $teachingAssignments = \Illuminate\Support\Facades\DB::table('classroom_subject_teacher')
            ->where('classroom_id', $classroom->id)
            ->join('users', 'classroom_subject_teacher.user_id', '=', 'users.id')
            ->join('subjects', 'classroom_subject_teacher.subject_id', '=', 'subjects.id')
            ->select(
                'classroom_subject_teacher.id',
                'users.id as user_id',
                'users.name as teacher_name',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
            )
            ->get();

        // Available teachers
        $availableTeachers = User::where('role', UserRole::Guru)
            ->where('is_active', true)
            ->select('id', 'name', 'username')
            ->orderBy('name')
            ->get();

        // Available subjects
        $availableSubjects = Subject::select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Classrooms/Show', [
            'classroom' => $classroom,
            'availableStudents' => $availableStudents,
            'teachingAssignments' => $teachingAssignments,
            'availableTeachers' => $availableTeachers,
            'availableSubjects' => $availableSubjects,
        ]);
    }

    /**
     * T1.2.8: Assign students to classroom.
     */
    public function assignStudents(Request $request, Classroom $classroom): RedirectResponse
    {
        $request->validate([
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['exists:users,id'],
        ]);

        $classroom->students()->syncWithoutDetaching($request->input('student_ids'));

        return back()->with('success', 'Siswa berhasil ditambahkan ke kelas.');
    }

    /**
     * Remove a student from classroom.
     */
    public function removeStudent(Classroom $classroom, User $user): RedirectResponse
    {
        $classroom->students()->detach($user->id);

        return back()->with('success', 'Siswa berhasil dihapus dari kelas.');
    }

    /**
     * T1.2.8: Assign teacher + subject to classroom.
     */
    public function assignTeacher(Request $request, Classroom $classroom): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        // Check duplicate
        $exists = \Illuminate\Support\Facades\DB::table('classroom_subject_teacher')
            ->where('classroom_id', $classroom->id)
            ->where('subject_id', $request->input('subject_id'))
            ->where('user_id', $request->input('user_id'))
            ->exists();

        if ($exists) {
            return back()->with('error', 'Guru sudah ditugaskan untuk mata pelajaran ini di kelas ini.');
        }

        \Illuminate\Support\Facades\DB::table('classroom_subject_teacher')->insert([
            'classroom_id' => $classroom->id,
            'subject_id' => $request->input('subject_id'),
            'user_id' => $request->input('user_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Guru berhasil ditugaskan.');
    }

    /**
     * Remove teacher assignment from classroom.
     */
    public function removeTeacher(Classroom $classroom, int $assignmentId): RedirectResponse
    {
        \Illuminate\Support\Facades\DB::table('classroom_subject_teacher')
            ->where('id', $assignmentId)
            ->where('classroom_id', $classroom->id)
            ->delete();

        return back()->with('success', 'Penugasan guru berhasil dihapus.');
    }
}
