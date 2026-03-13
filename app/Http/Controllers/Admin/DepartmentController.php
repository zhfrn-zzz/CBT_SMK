<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        $departments = Department::withCount(['classrooms', 'subjects'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Departments/Create');
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return redirect()->route('admin.departments.index')
            ->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('Admin/Departments/Edit', [
            'department' => $department,
        ]);
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()->route('admin.departments.index')
            ->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()->route('admin.departments.index')
            ->with('success', 'Jurusan berhasil dihapus.');
    }
}
