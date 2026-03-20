<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NilaiRaporRekapSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private Collection $students;

    /** @var array<int, string> */
    private array $subjects;

    /** @var array<int, array<int, float>> */
    private array $scores;

    public function __construct(
        private readonly Classroom $classroom,
        private readonly AcademicYear $academicYear,
    ) {
        $this->prepare();
    }

    private function prepare(): void
    {
        $this->students = $this->classroom->students()->orderBy('name')->get();

        $attempts = ExamAttempt::query()
            ->join('exam_sessions', 'exam_attempts.exam_session_id', '=', 'exam_sessions.id')
            ->join('exam_session_classroom', 'exam_sessions.id', '=', 'exam_session_classroom.exam_session_id')
            ->join('subjects', 'exam_sessions.subject_id', '=', 'subjects.id')
            ->where('exam_session_classroom.classroom_id', $this->classroom->id)
            ->where('exam_sessions.academic_year_id', $this->academicYear->id)
            ->where('exam_sessions.is_results_published', true)
            ->whereIn('exam_attempts.status', ['submitted', 'graded'])
            ->whereNotNull('exam_attempts.score')
            ->select([
                'exam_attempts.user_id',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                DB::raw('AVG(exam_attempts.score) as avg_score'),
            ])
            ->groupBy('exam_attempts.user_id', 'subjects.id', 'subjects.name')
            ->get();

        $this->subjects = $attempts->pluck('subject_name', 'subject_id')->toArray();

        $this->scores = [];
        foreach ($attempts as $row) {
            $this->scores[$row->user_id][$row->subject_id] = round((float) $row->avg_score, 2);
        }
    }

    public function headings(): array
    {
        return array_merge(['No', 'Nama', 'Username'], array_values($this->subjects));
    }

    public function collection(): Collection
    {
        if ($this->students->isEmpty()) {
            return collect([['', 'Belum ada data siswa', '', ...array_fill(0, count($this->subjects), '-')]]);
        }

        return $this->students->values()->map(function (User $student, int $idx) {
            $row = [$idx + 1, $student->name, $student->username];
            foreach (array_keys($this->subjects) as $subjectId) {
                $row[] = $this->scores[$student->id][$subjectId] ?? '-';
            }

            return $row;
        });
    }

    public function title(): string
    {
        return 'Rekap';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
