<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamAttempt;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NilaiRaporDetailSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly Classroom $classroom,
        private readonly AcademicYear $academicYear,
    ) {}

    public function headings(): array
    {
        return ['No', 'Nama Siswa', 'Username', 'Mata Pelajaran', 'Nama Ujian', 'Nilai', 'Status', 'Tanggal'];
    }

    public function collection(): Collection
    {
        $attempts = ExamAttempt::query()
            ->join('exam_sessions', 'exam_attempts.exam_session_id', '=', 'exam_sessions.id')
            ->join('exam_session_classroom', 'exam_sessions.id', '=', 'exam_session_classroom.exam_session_id')
            ->join('subjects', 'exam_sessions.subject_id', '=', 'subjects.id')
            ->join('users', 'exam_attempts.user_id', '=', 'users.id')
            ->where('exam_session_classroom.classroom_id', $this->classroom->id)
            ->where('exam_sessions.academic_year_id', $this->academicYear->id)
            ->where('exam_sessions.is_results_published', true)
            ->whereIn('exam_attempts.status', ['submitted', 'graded'])
            ->select([
                'users.name as student_name',
                'users.username',
                'subjects.name as subject_name',
                'exam_sessions.name as exam_name',
                'exam_attempts.score',
                'exam_attempts.status',
                'exam_attempts.submitted_at',
            ])
            ->orderBy('users.name')
            ->orderBy('subjects.name')
            ->get();

        if ($attempts->isEmpty()) {
            return collect([['', 'Belum ada data', '', '', '', '', '', '']]);
        }

        return $attempts->values()->map(function ($row, int $idx) {
            return [
                $idx + 1,
                $row->student_name,
                $row->username,
                $row->subject_name,
                $row->exam_name,
                $row->score !== null ? (float) $row->score : '-',
                $row->status === 'graded' ? 'Dinilai' : 'Dikumpulkan',
                $row->submitted_at ? Carbon::parse($row->submitted_at)->format('d/m/Y H:i') : '-',
            ];
        });
    }

    public function title(): string
    {
        return 'Detail';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
