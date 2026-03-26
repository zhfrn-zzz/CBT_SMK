<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuruRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
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

class GuruController extends Controller
{
    public function index(Request $request): Response
    {
        $activeYear = AcademicYear::active()->first();

        $query = User::where('role', UserRole::Guru)
            ->with([
                'teachingAssignments' => function ($q) use ($activeYear) {
                    $q->with(['classroom:id,name', 'subject:id,name']);
                    if ($activeYear) {
                        $q->whereHas('classroom', fn ($cq) => $cq->where('academic_year_id', $activeYear->id));
                    }
                },
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('teachingAssignments', fn ($q) => $q->where('subject_id', $request->integer('subject_id')));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('teachingAssignments.classroom', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }

        $gurus = $query->latest()->paginate(15)->withQueryString();

        $gurus->through(function (User $user) {
            $data = $user->toArray();
            $data['teachings'] = $user->teachingAssignments->map(fn ($ta) => [
                'classroom_name' => $ta->classroom?->name,
                'subject_name' => $ta->subject?->name,
            ])->toArray();

            return $data;
        });

        $subjects = Subject::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('Admin/Guru/Index', [
            'gurus' => $gurus,
            'filters' => $request->only(['search', 'subject_id', 'department_id']),
            'subjects' => $subjects,
        ]);
    }

    public function create(): Response
    {
        $classrooms = Classroom::select('id', 'name', 'academic_year_id', 'department_id')
            ->with('department:id,name')
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (Classroom $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'department' => $c->department?->name,
            ]);

        $subjects = Subject::select('id', 'name', 'code')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return Inertia::render('Admin/Guru/Create', [
            'classrooms' => $classrooms,
            'subjects' => $subjects,
        ]);
    }

    public function store(GuruRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $password = $validated['password'] ?? null;
        $generatedPassword = null;

        if (empty($password)) {
            $password = Str::random(8);
            $generatedPassword = $password;
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($password),
            'role' => UserRole::Guru,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['teachings'])) {
            foreach ($validated['teachings'] as $teaching) {
                TeachingAssignment::firstOrCreate([
                    'user_id' => $user->id,
                    'classroom_id' => $teaching['classroom_id'],
                    'subject_id' => $teaching['subject_id'],
                ]);
            }
        }

        $redirect = redirect()->route('admin.guru.index')
            ->with('success', 'Guru berhasil ditambahkan.');

        if ($generatedPassword) {
            $redirect->with('generated_password', $generatedPassword)
                ->with('generated_user_name', $user->name)
                ->with('generated_user_username', $user->username);
        }

        return $redirect;
    }

    public function edit(User $guru): Response
    {
        abort_unless($guru->role === UserRole::Guru, 404);

        $guru->load(['teachingAssignments' => function ($q) {
            $q->with(['classroom:id,name', 'subject:id,name']);
        }]);

        $classrooms = Classroom::select('id', 'name', 'academic_year_id', 'department_id')
            ->with('department:id,name')
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (Classroom $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'department' => $c->department?->name,
            ]);

        $subjects = Subject::select('id', 'name', 'code')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return Inertia::render('Admin/Guru/Edit', [
            'guru' => [
                ...$guru->toArray(),
                'teachings' => $guru->teachingAssignments->map(fn ($ta) => [
                    'classroom_id' => $ta->classroom_id,
                    'subject_id' => $ta->subject_id,
                    'classroom_name' => $ta->classroom?->name,
                    'subject_name' => $ta->subject?->name,
                ])->toArray(),
            ],
            'classrooms' => $classrooms,
            'subjects' => $subjects,
        ]);
    }

    public function update(GuruRequest $request, User $guru): RedirectResponse
    {
        abort_unless($guru->role === UserRole::Guru, 404);

        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $guru->update($updateData);

        // Sync teaching assignments
        $existingIds = $guru->teachingAssignments()->pluck('id')->toArray();
        $keepIds = [];

        if (! empty($validated['teachings'])) {
            foreach ($validated['teachings'] as $teaching) {
                $assignment = TeachingAssignment::firstOrCreate([
                    'user_id' => $guru->id,
                    'classroom_id' => $teaching['classroom_id'],
                    'subject_id' => $teaching['subject_id'],
                ]);
                $keepIds[] = $assignment->id;
            }
        }

        // Delete removed assignments
        $toDelete = array_diff($existingIds, $keepIds);
        if (! empty($toDelete)) {
            TeachingAssignment::whereIn('id', $toDelete)->delete();
        }

        return redirect()->route('admin.guru.index')
            ->with('success', 'Data guru berhasil diperbarui.');
    }

    public function destroy(User $guru): RedirectResponse
    {
        abort_unless($guru->role === UserRole::Guru, 404);

        if ($guru->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        // Check for active exam sessions
        $activeExams = $guru->examSessions()
            ->whereIn('status', ['active', 'scheduled'])
            ->count();

        if ($activeExams > 0) {
            return back()->with('error', 'Tidak dapat menghapus guru yang memiliki ujian aktif.');
        }

        $guru->teachingAssignments()->delete();
        $guru->delete();

        return redirect()->route('admin.guru.index')
            ->with('success', 'Guru berhasil dihapus.');
    }

    public function resetPassword(User $guru, AuditService $auditService): JsonResponse
    {
        abort_unless($guru->role === UserRole::Guru, 403, 'Hanya guru yang dapat direset passwordnya.');

        $password = Str::random(8);
        $guru->update(['password' => Hash::make($password)]);

        $auditService->log(
            'password_reset',
            User::class,
            $guru->id,
            ['target_user' => $guru->name],
            "Password reset untuk {$guru->name} oleh " . auth()->user()->name,
        );

        return response()->json([
            'password' => $password,
            'name' => $guru->name,
            'username' => $guru->username,
        ]);
    }
}
