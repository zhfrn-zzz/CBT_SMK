<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Semester;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        $academicYears = AcademicYear::withCount('classrooms')
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/AcademicYears/Index', [
            'academicYears' => $academicYears,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AcademicYears/Create', [
            'semesters' => collect(Semester::cases())->map(fn (Semester $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isActive = (bool) ($data['is_active'] ?? false);

        DB::transaction(function () use ($data, $isActive) {
            if ($isActive) {
                AcademicYear::where('is_active', true)->update(['is_active' => false]);
            }

            $data['is_active'] = $isActive;
            AcademicYear::create($data);
        });

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function edit(AcademicYear $academicYear): Response
    {
        return Inertia::render('Admin/AcademicYears/Edit', [
            'academicYear' => $academicYear,
            'semesters' => collect(Semester::cases())->map(fn (Semester $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validated();
        $isActive = (bool) ($data['is_active'] ?? false);

        DB::transaction(function () use ($data, $isActive, $academicYear) {
            if ($isActive) {
                AcademicYear::where('is_active', true)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_active' => false]);
            }

            $data['is_active'] = $isActive;
            $academicYear->update($data);
        });

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil diperbarui.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->delete();

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil dihapus.');
    }
}
