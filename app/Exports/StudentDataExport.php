<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentDataExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        private readonly ?Classroom $classroom = null,
        private readonly ?AcademicYear $academicYear = null,
    ) {}

    public function headings(): array
    {
        return ['No', 'NIS', 'Nama', 'Email', 'Kelas', 'Jurusan', 'Tahun Ajaran', 'Status'];
    }

    public function collection(): Collection
    {
        $query = User::query()
            ->where('role', 'siswa')
            ->with(['classrooms.department', 'classrooms.academicYear'])
            ->orderBy('name');

        if ($this->classroom) {
            $query->whereHas('classrooms', fn ($q) => $q->where('classrooms.id', $this->classroom->id));
        }

        if ($this->academicYear) {
            $query->whereHas('classrooms', fn ($q) => $q->where('classrooms.academic_year_id', $this->academicYear->id));
        }

        return $query->get()->values()->map(function (User $user, int $idx) {
            $classroom = $this->classroom
                ? $user->classrooms->firstWhere('id', $this->classroom->id)
                : $user->classrooms->first();

            return [
                $idx + 1,
                $user->username,
                $user->name,
                $user->email ?? '-',
                $classroom?->name ?? '-',
                $classroom?->department?->name ?? '-',
                $classroom?->academicYear?->name ?? '-',
                $user->is_active ? 'Aktif' : 'Nonaktif',
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
