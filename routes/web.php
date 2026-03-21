<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DataExchangeController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Guru\AnnouncementController as GuruAnnouncementController;
use App\Http\Controllers\Guru\AssignmentController as GuruAssignmentController;
use App\Http\Controllers\Guru\AttendanceController as GuruAttendanceController;
use App\Http\Controllers\Guru\CompetencyController;
use App\Http\Controllers\Guru\DashboardController as GuruDashboardController;
use App\Http\Controllers\Guru\DiscussionController as GuruDiscussionController;
use App\Http\Controllers\Guru\ExamSessionController;
use App\Http\Controllers\Guru\GradingController;
use App\Http\Controllers\Guru\ItemAnalysisController;
use App\Http\Controllers\Guru\MaterialController as GuruMaterialController;
use App\Http\Controllers\Guru\ProctorController;
use App\Http\Controllers\Guru\QuestionBankController;
use App\Http\Controllers\Guru\QuestionController;
use App\Http\Controllers\Guru\QuestionImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Siswa\AnnouncementController as SiswaAnnouncementController;
use App\Http\Controllers\Siswa\AssignmentController as SiswaAssignmentController;
use App\Http\Controllers\Siswa\AttendanceController as SiswaAttendanceController;
use App\Http\Controllers\Siswa\DashboardController as SiswaDashboardController;
use App\Http\Controllers\Siswa\DiscussionController as SiswaDiscussionController;
use App\Http\Controllers\Siswa\ExamController;
use App\Http\Controllers\Siswa\ExamResultController;
use App\Http\Controllers\Siswa\MaterialController as SiswaMaterialController;
use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\PublicHomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Generic /dashboard → redirect to role-based dashboard
    Route::get('dashboard', function () {
        return redirect(auth()->user()->dashboardRoute());
    })->name('dashboard');

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        // User management
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/import', [UserImportController::class, 'import'])->middleware('throttle:bulk-import')->name('users.import');

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

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        Route::post('backup', [BackupController::class, 'store'])->name('backup.store');

        // Analytics
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('analytics/classroom/{classroom}', [AnalyticsController::class, 'classroomDetail'])->name('analytics.classroom');

        // Data Exchange
        Route::get('data-exchange/export-students', [DataExchangeController::class, 'index'])->name('data-exchange.index');
        Route::get('data-exchange/export-students/download', [DataExchangeController::class, 'exportStudents'])->name('data-exchange.export-students');
        Route::get('data-exchange/template', [DataExchangeController::class, 'downloadTemplate'])->name('data-exchange.template');
        Route::post('data-exchange/import', [DataExchangeController::class, 'importStudents'])->middleware('throttle:bulk-import')->name('data-exchange.import');
        Route::post('analytics/export-rapor', [DataExchangeController::class, 'exportRapor'])->name('analytics.export-rapor');
        Route::get('analytics/download-export/{filename}', [DataExchangeController::class, 'downloadExport'])->name('analytics.download-export');
    });

    // Guru routes
    Route::middleware('role:guru')->prefix('guru')->name('guru.')->group(function () {
        Route::get('dashboard', GuruDashboardController::class)->name('dashboard');

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
        Route::post('bank-soal/{bankSoal}/soal/import', [QuestionImportController::class, 'import'])->middleware('throttle:bulk-import')->name('bank-soal.soal.import');

        // Ujian / Exam Sessions
        Route::resource('ujian', ExamSessionController::class)
            ->parameters(['ujian' => 'ujian']);
        Route::patch('ujian/{ujian}/status', [ExamSessionController::class, 'updateStatus'])->name('ujian.update-status');
        Route::get('ujian/{ujian}/print-pdf', [ExamSessionController::class, 'printPdf'])->name('ujian.print-pdf');
        Route::get('ujian/{ujian}/remedial', [ExamSessionController::class, 'createRemedial'])->name('ujian.create-remedial');
        Route::post('ujian/{ujian}/remedial', [ExamSessionController::class, 'storeRemedial'])->name('ujian.store-remedial');

        // Penilaian / Grading
        Route::get('grading', [GradingController::class, 'index'])->name('grading.index');
        Route::get('grading/{examSession}', [GradingController::class, 'show'])->name('grading.show');
        Route::get('grading/{examSession}/attempt/{attempt}', [GradingController::class, 'manualGrading'])->name('grading.manual');
        Route::get('grading/{examSession}/attempt/{attempt}/activity-log', [GradingController::class, 'activityLog'])->name('grading.activity-log');
        Route::post('grading/{examSession}/answer/{answer}', [GradingController::class, 'saveGrade'])->name('grading.save-grade');
        Route::patch('grading/{examSession}/publish', [GradingController::class, 'publishResults'])->name('grading.publish');
        Route::patch('grading/{examSession}/unpublish', [GradingController::class, 'unpublishResults'])->name('grading.unpublish');
        Route::get('grading/{examSession}/export', [GradingController::class, 'exportResults'])->name('grading.export');

        // Item Analysis
        Route::get('grading/{examSession}/item-analysis', [ItemAnalysisController::class, 'show'])->name('grading.item-analysis');
        Route::post('grading/{examSession}/item-analysis/refresh', [ItemAnalysisController::class, 'refresh'])->name('grading.item-analysis.refresh');

        // Kompetensi Dasar
        Route::get('bank-soal/{bankSoal}/kompetensi', [CompetencyController::class, 'index'])->name('bank-soal.kompetensi.index');
        Route::post('bank-soal/{bankSoal}/kompetensi', [CompetencyController::class, 'store'])->name('bank-soal.kompetensi.store');
        Route::put('bank-soal/{bankSoal}/kompetensi/{competency}', [CompetencyController::class, 'update'])->name('bank-soal.kompetensi.update');
        Route::delete('bank-soal/{bankSoal}/kompetensi/{competency}', [CompetencyController::class, 'destroy'])->name('bank-soal.kompetensi.destroy');
        Route::post('bank-soal/{bankSoal}/soal/{soal}/tag-kompetensi', [CompetencyController::class, 'tagQuestion'])->name('bank-soal.soal.tag-kompetensi');

        // Proctor Dashboard
        Route::get('ujian/{ujian}/proctor', [ProctorController::class, 'show'])->name('ujian.proctor');
        Route::post('ujian/{ujian}/proctor/extend-time', [ProctorController::class, 'extendTime'])->name('ujian.proctor.extend-time');
        Route::post('ujian/{ujian}/proctor/terminate', [ProctorController::class, 'terminate'])->name('ujian.proctor.terminate');
        Route::post('ujian/{ujian}/proctor/invalidate-question', [ProctorController::class, 'invalidateQuestion'])->name('ujian.proctor.invalidate-question');

        // === Phase 3: LMS ===
        // Materi
        Route::resource('materi', GuruMaterialController::class)
            ->parameters(['materi' => 'material']);
        Route::get('materi/{material}/download', [GuruMaterialController::class, 'download'])->name('materi.download');
        Route::post('materi/reorder', [GuruMaterialController::class, 'reorder'])->name('materi.reorder');

        // Tugas
        Route::resource('tugas', GuruAssignmentController::class)
            ->parameters(['tugas' => 'assignment']);
        Route::get('tugas/{assignment}/download', [GuruAssignmentController::class, 'download'])->name('tugas.download');
        Route::put('tugas/submissions/{submission}/grade', [GuruAssignmentController::class, 'grade'])->name('tugas.grade');
        Route::get('tugas/submissions/{submission}/download', [GuruAssignmentController::class, 'downloadSubmission'])->name('tugas.download-submission');

        // Forum
        Route::resource('forum', GuruDiscussionController::class)
            ->parameters(['forum' => 'thread'])
            ->only(['index', 'show', 'store', 'destroy']);
        Route::post('forum/{thread}/reply', [GuruDiscussionController::class, 'reply'])->name('forum.reply');
        Route::delete('forum/reply/{reply}', [GuruDiscussionController::class, 'deleteReply'])->name('forum.delete-reply');
        Route::post('forum/{thread}/toggle-pin', [GuruDiscussionController::class, 'togglePin'])->name('forum.toggle-pin');
        Route::post('forum/{thread}/toggle-lock', [GuruDiscussionController::class, 'toggleLock'])->name('forum.toggle-lock');

        // Pengumuman
        Route::resource('pengumuman', GuruAnnouncementController::class)
            ->parameters(['pengumuman' => 'announcement']);
        Route::post('pengumuman/{announcement}/toggle-pin', [GuruAnnouncementController::class, 'togglePin'])->name('pengumuman.toggle-pin');

        // Presensi
        Route::resource('presensi', GuruAttendanceController::class)
            ->parameters(['presensi' => 'attendance'])
            ->only(['index', 'create', 'store', 'show']);
        Route::post('presensi/{attendance}/close', [GuruAttendanceController::class, 'close'])->name('presensi.close');
        Route::post('presensi/{attendance}/regenerate-code', [GuruAttendanceController::class, 'regenerateCode'])->name('presensi.regenerate-code');
        Route::put('presensi/{attendance}/status', [GuruAttendanceController::class, 'updateStatus'])->name('presensi.update-status');
        Route::get('presensi-recap', [GuruAttendanceController::class, 'recap'])->name('presensi.recap');
        Route::get('presensi-recap/export', [GuruAttendanceController::class, 'exportRecap'])->name('presensi.export-recap');
    });

    // Siswa routes
    Route::middleware('role:siswa')->prefix('siswa')->name('siswa.')->group(function () {
        Route::get('dashboard', SiswaDashboardController::class)->name('dashboard');

        // Ujian
        Route::get('ujian', [ExamController::class, 'index'])->name('ujian.index');
        Route::get('ujian/{ujian}/verify-token', [ExamController::class, 'showVerifyToken'])->name('ujian.verify-token');
        Route::post('ujian/{ujian}/verify-token', [ExamController::class, 'verifyToken']);
        Route::get('ujian/{ujian}/start', [ExamController::class, 'start'])->name('ujian.start');
        Route::get('ujian/{ujian}/exam', [ExamController::class, 'exam'])->name('ujian.exam');
        Route::post('ujian/{ujian}/save-answers', [ExamController::class, 'saveAnswers'])->middleware('throttle:exam-save')->name('ujian.save-answers');
        Route::post('ujian/{ujian}/submit', [ExamController::class, 'submit'])->name('ujian.submit');

        // Nilai / Results
        Route::get('nilai', [ExamResultController::class, 'index'])->name('nilai.index');
        Route::get('nilai/{attempt}', [ExamResultController::class, 'show'])->name('nilai.show');

        // === Phase 3: LMS ===
        // Materi
        Route::get('materi', [SiswaMaterialController::class, 'index'])->name('materi.index');
        Route::get('materi/{material}', [SiswaMaterialController::class, 'show'])->name('materi.show');
        Route::get('materi/{material}/download', [SiswaMaterialController::class, 'download'])->name('materi.download');
        Route::post('materi/{material}/complete', [SiswaMaterialController::class, 'complete'])->name('materi.complete');

        // Tugas
        Route::get('tugas', [SiswaAssignmentController::class, 'index'])->name('tugas.index');
        Route::get('tugas/{assignment}', [SiswaAssignmentController::class, 'show'])->name('tugas.show');
        Route::post('tugas/{assignment}/submit', [SiswaAssignmentController::class, 'submit'])->name('tugas.submit');
        Route::get('tugas/{assignment}/download', [SiswaAssignmentController::class, 'download'])->name('tugas.download');

        // Forum
        Route::get('forum', [SiswaDiscussionController::class, 'index'])->name('forum.index');
        Route::get('forum/{thread}', [SiswaDiscussionController::class, 'show'])->name('forum.show');
        Route::post('forum', [SiswaDiscussionController::class, 'store'])->name('forum.store');
        Route::delete('forum/{thread}', [SiswaDiscussionController::class, 'destroy'])->name('forum.destroy');
        Route::post('forum/{thread}/reply', [SiswaDiscussionController::class, 'reply'])->name('forum.reply');
        Route::delete('forum/reply/{reply}', [SiswaDiscussionController::class, 'deleteReply'])->name('forum.delete-reply');

        // Pengumuman
        Route::get('pengumuman', [SiswaAnnouncementController::class, 'index'])->name('pengumuman.index');
        Route::get('pengumuman/{announcement}', [SiswaAnnouncementController::class, 'show'])->name('pengumuman.show');

        // Presensi
        Route::get('presensi', [SiswaAttendanceController::class, 'index'])->name('presensi.index');
        Route::post('presensi/check-in', [SiswaAttendanceController::class, 'checkIn'])->name('presensi.check-in');
    });

    // API-style routes (for fire-and-forget from exam interface)
    Route::middleware('auth')->prefix('api/exam')->group(function () {
        Route::post('log-activity', [ExamController::class, 'logActivity'])->middleware('throttle:exam-activity')->name('api.exam.log-activity');
    });

    // Notification routes (shared across all roles)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/list', [NotificationController::class, 'list'])->name('list');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
