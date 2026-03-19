<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::query()->with('user:id,name,role');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $auditLogs = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $auditableTypesMap = self::auditableTypesMap();
        $auditLogs->getCollection()->transform(function ($log) use ($auditableTypesMap) {
            $log->auditable_type_label = $log->auditable_type ? ($auditableTypesMap[$log->auditable_type] ?? $log->auditable_type) : null;

            return $log;
        });

        $users = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/AuditLog/Index', [
            'auditLogs' => $auditLogs,
            'users' => $users,
            'filters' => $request->only(['user_id', 'action', 'auditable_type', 'date_from', 'date_to']),
            'actionTypes' => ['created', 'updated', 'deleted', 'login', 'export', 'import'],
            'auditableTypes' => $auditableTypesMap,
        ]);
    }

    private static function auditableTypesMap(): array
    {
        return [
            'App\\Models\\User' => 'Pengguna',
            'App\\Models\\ExamSession' => 'Sesi Ujian',
            'App\\Models\\QuestionBank' => 'Bank Soal',
            'App\\Models\\Question' => 'Soal',
            'App\\Models\\Material' => 'Materi',
            'App\\Models\\Assignment' => 'Tugas',
            'App\\Models\\Announcement' => 'Pengumuman',
            'App\\Models\\Classroom' => 'Kelas',
            'App\\Models\\Subject' => 'Mata Pelajaran',
            'App\\Models\\AcademicYear' => 'Tahun Ajaran',
            'App\\Models\\Department' => 'Jurusan',
        ];
    }
}
