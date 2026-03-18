<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceRecapExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(private readonly Collection $recap) {}

    public function headings(): array
    {
        return ['No', 'Nama Siswa', 'Username', 'Total Pertemuan', 'Hadir', 'Izin', 'Sakit', 'Alfa', '% Kehadiran'];
    }

    public function collection(): Collection
    {
        return $this->recap->values()->map(function ($row, $index) {
            return [
                'no' => $index + 1,
                'name' => $row['user']->name,
                'username' => $row['user']->username,
                'total_meetings' => $row['total_meetings'],
                'hadir' => $row['hadir'],
                'izin' => $row['izin'],
                'sakit' => $row['sakit'],
                'alfa' => $row['alfa'],
                'percentage' => $row['percentage'].'%',
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
