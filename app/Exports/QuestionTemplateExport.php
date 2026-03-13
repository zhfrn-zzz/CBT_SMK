<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuestionTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'soal',
            'tipe',
            'opsi_a',
            'opsi_b',
            'opsi_c',
            'opsi_d',
            'opsi_e',
            'jawaban_benar',
            'bobot',
            'pembahasan',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Apa kepanjangan dari HTML?',
                'PG',
                'Hyper Text Markup Language',
                'High Tech Modern Language',
                'Hyper Transfer Markup Language',
                'Home Tool Markup Language',
                '',
                'A',
                1,
                'HTML adalah singkatan dari Hyper Text Markup Language.',
            ],
            [
                'CSS digunakan untuk mengatur tampilan halaman web.',
                'BS',
                'Benar',
                'Salah',
                '',
                '',
                '',
                'A',
                1,
                'CSS (Cascading Style Sheets) memang digunakan untuk styling.',
            ],
            [
                'Jelaskan perbedaan antara TCP dan UDP!',
                'Esai',
                '',
                '',
                '',
                '',
                '',
                '',
                2,
                'TCP bersifat connection-oriented, UDP bersifat connectionless.',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
