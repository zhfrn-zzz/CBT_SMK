<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AcademicYear;
use App\Models\Classroom;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class NilaiRaporExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly Classroom $classroom,
        private readonly AcademicYear $academicYear,
    ) {}

    public function sheets(): array
    {
        return [
            new NilaiRaporRekapSheet($this->classroom, $this->academicYear),
            new NilaiRaporDetailSheet($this->classroom, $this->academicYear),
        ];
    }
}
