<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Generic /dashboard → redirect to role-based dashboard
    Route::get('dashboard', function () {
        return redirect(auth()->user()->dashboardRoute());
    })->name('dashboard');

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('dashboard', 'Admin/Dashboard')->name('dashboard');

        // User management
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/import', [UserImportController::class, 'import'])->name('users.import');

        // Academic structure
        Route::resource('academic-years', AcademicYearController::class)->except(['show']);
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::resource('subjects', SubjectController::class)->except(['show']);

        // Classrooms (with show for assignment management)
        Route::resource('classrooms', ClassroomController::class);
        Route::post('classrooms/{classroom}/assign-students', [ClassroomController::class, 'assignStudents'])->name('classrooms.assign-students');
        Route::delete('classrooms/{classroom}/remove-student/{user}', [ClassroomController::class, 'removeStudent'])->name('classrooms.remove-student');
        Route::post('classrooms/{classroom}/assign-teacher', [ClassroomController::class, 'assignTeacher'])->name('classrooms.assign-teacher');
        Route::delete('classrooms/{classroom}/remove-teacher/{assignmentId}', [ClassroomController::class, 'removeTeacher'])->name('classrooms.remove-teacher');
    });

    // Guru routes
    Route::middleware('role:guru')->prefix('guru')->name('guru.')->group(function () {
        Route::inertia('dashboard', 'Guru/Dashboard')->name('dashboard');
    });

    // Siswa routes
    Route::middleware('role:siswa')->prefix('siswa')->name('siswa.')->group(function () {
        Route::inertia('dashboard', 'Siswa/Dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
