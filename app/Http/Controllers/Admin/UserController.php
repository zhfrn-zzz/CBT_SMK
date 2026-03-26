<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $activeYear = AcademicYear::active()->first();

        $users = User::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('role'), function ($query, $role) {
                $query->where('role', $role);
            })
            ->when($request->input('department_id'), function ($query, $departmentId) use ($activeYear) {
                $query->where('role', UserRole::Siswa)
                    ->whereHas('classrooms', function ($q) use ($departmentId, $activeYear) {
                        $q->where('department_id', $departmentId);
                        if ($activeYear) {
                            $q->where('academic_year_id', $activeYear->id);
                        }
                    });
            })
            ->when($request->input('classroom_id'), function ($query, $classroomId) {
                $query->where('role', UserRole::Siswa)
                    ->whereHas('classrooms', fn ($q) => $q->where('classrooms.id', $classroomId));
            })
            ->with([
                // F2.4: Select only needed columns to reduce props size
                'classrooms' => function ($q) use ($activeYear) {
                    $q->select('classrooms.id', 'classrooms.name', 'classrooms.department_id');
                    if ($activeYear) {
                        $q->where('academic_year_id', $activeYear->id);
                    }
                    $q->with('department:id,name');
                },
                'teachingAssignments' => function ($q) use ($activeYear) {
                    $q->with([
                        'classroom:id,name',
                        'subject:id,name',
                    ]);
                    if ($activeYear) {
                        $q->whereHas('classroom', fn ($cq) => $cq->where('academic_year_id', $activeYear->id));
                    }
                },
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Flatten user data with classroom/teaching info
        $users->through(function (User $user) {
            $data = $user->toArray();

            if ($user->role === UserRole::Siswa) {
                $classroom = $user->classrooms->first();
                $data['classroom_name'] = $classroom?->name;
                $data['department_name'] = $classroom?->department?->name;
            }

            if ($user->role === UserRole::Guru) {
                $data['teachings'] = $user->teachingAssignments->map(fn ($ta) => [
                    'classroom_name' => $ta->classroom?->name,
                    'subject_name' => $ta->subject?->name,
                ])->toArray();
            }

            return $data;
        });

        // Filter options
        $departments = Department::select('id', 'name', 'code')->orderBy('name')->get();
        $classrooms = $activeYear
            ? Classroom::where('academic_year_id', $activeYear->id)
                ->select('id', 'name', 'department_id')
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'department_id', 'classroom_id']),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
            'departments' => $departments,
            'classrooms' => $classrooms,
        ]);
    }

    public function create(): Response
    {
        $academicYears = AcademicYear::select('id', 'name', 'semester', 'is_active')
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->get();

        $departments = Department::select('id', 'name', 'code')->orderBy('name')->get();

        // F2.3: Select only needed columns with safety limit
        $classrooms = Classroom::select('id', 'name', 'academic_year_id', 'department_id')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $subjects = Subject::select('id', 'name', 'code', 'department_id')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return Inertia::render('Admin/Users/Create', [
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
            'academicYears' => $academicYears,
            'departments' => $departments,
            'classrooms' => $classrooms,
            'subjects' => $subjects,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Auto-generate password for siswa if empty
        $generatedPassword = null;
        $password = $validated['password'] ?? null;

        if ($validated['role'] === UserRole::Siswa->value && empty($password)) {
            $password = Str::random(8);
            $generatedPassword = $password;
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($password),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Siswa: assign to classroom
        if ($validated['role'] === 'siswa' && ! empty($validated['classroom_id'])) {
            $user->classrooms()->attach($validated['classroom_id']);
        }

        // Guru: assign teaching assignments
        if ($validated['role'] === 'guru' && ! empty($validated['teachings'])) {
            foreach ($validated['teachings'] as $teaching) {
                TeachingAssignment::firstOrCreate([
                    'user_id' => $user->id,
                    'classroom_id' => $teaching['classroom_id'],
                    'subject_id' => $teaching['subject_id'],
                ]);
            }
        }

        $redirect = redirect()->route('admin.users.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');

        if ($generatedPassword) {
            $redirect->with('generated_password', $generatedPassword)
                ->with('generated_user_name', $user->name)
                ->with('generated_user_username', $user->username);
        }

        return $redirect;
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        unset($data['classroom_id'], $data['teachings']);

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }

    public function resetPassword(User $user, AuditService $auditService): JsonResponse
    {
        abort_unless($user->isSiswa(), 403, 'Hanya password siswa yang dapat direset.');

        $password = Str::random(8);
        $user->update(['password' => Hash::make($password)]);

        $auditService->log(
            'password_reset',
            User::class,
            $user->id,
            ['target_user' => $user->name],
            "Password reset untuk {$user->name} oleh " . auth()->user()->name,
        );

        return response()->json([
            'password' => $password,
            'name' => $user->name,
            'username' => $user->username,
        ]);
    }
}
