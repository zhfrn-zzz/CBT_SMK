<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Guru\ExamSessionController;
use App\Http\Controllers\Guru\QuestionBankController;
use App\Http\Controllers\Guru\QuestionController;
use App\Http\Controllers\Guru\QuestionImportController;
use App\Http\Controllers\Siswa\ExamController;
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

        // Bank Soal
        Route::resource('bank-soal', QuestionBankController::class)
            ->parameters(['bank-soal' => 'bankSoal']);

        // Soal dalam bank soal (nested)
        Route::get('bank-soal/{bankSoal}/soal/create', [QuestionController::class, 'create'])->name('bank-soal.soal.create');
        Route::post('bank-soal/{bankSoal}/soal', [QuestionController::class, 'store'])->name('bank-soal.soal.store');
        Route::get('bank-soal/{bankSoal}/soal/{soal}/edit', [QuestionController::class, 'edit'])->name('bank-soal.soal.edit');
        Route::put('bank-soal/{bankSoal}/soal/{soal}', [QuestionController::class, 'update'])->name('bank-soal.soal.update');
        Route::delete('bank-soal/{bankSoal}/soal/{soal}', [QuestionController::class, 'destroy'])->name('bank-soal.soal.destroy');

        // Image upload for Tiptap editor
        Route::post('soal/upload-image', [QuestionController::class, 'uploadImage'])->name('soal.upload-image');

        // Import soal
        Route::get('bank-soal/{bankSoal}/soal/template-download', [QuestionImportController::class, 'template'])->name('bank-soal.soal.template');
        Route::post('bank-soal/{bankSoal}/soal/import', [QuestionImportController::class, 'import'])->name('bank-soal.soal.import');

        // Ujian / Exam Sessions
        Route::resource('ujian', ExamSessionController::class)
            ->parameters(['ujian' => 'ujian']);
        Route::patch('ujian/{ujian}/status', [ExamSessionController::class, 'updateStatus'])->name('ujian.update-status');
    });

    // Siswa routes
    Route::middleware('role:siswa')->prefix('siswa')->name('siswa.')->group(function () {
        Route::inertia('dashboard', 'Siswa/Dashboard')->name('dashboard');

        // Ujian
        Route::get('ujian', [ExamController::class, 'index'])->name('ujian.index');
        Route::get('ujian/{ujian}/verify-token', [ExamController::class, 'showVerifyToken'])->name('ujian.verify-token');
        Route::post('ujian/{ujian}/verify-token', [ExamController::class, 'verifyToken']);
        Route::get('ujian/{ujian}/start', [ExamController::class, 'start'])->name('ujian.start');
        Route::get('ujian/{ujian}/exam', [ExamController::class, 'exam'])->name('ujian.exam');
        Route::post('ujian/{ujian}/save-answers', [ExamController::class, 'saveAnswers'])->name('ujian.save-answers');
        Route::post('ujian/{ujian}/submit', [ExamController::class, 'submit'])->name('ujian.submit');
    });

    // API-style routes (for fire-and-forget from exam interface)
    Route::middleware('auth')->prefix('api/exam')->group(function () {
        Route::post('log-activity', [ExamController::class, 'logActivity'])->name('api.exam.log-activity');
    });
});

require __DIR__.'/settings.php';
