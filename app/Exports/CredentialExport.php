<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CredentialExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        private readonly array $credentials,
    ) {}

    public function headings(): array
    {
        return ['No', 'NIS', 'Nama', 'Password'];
    }

    public function array(): array
    {
        return array_map(function (array $credential, int $index) {
            return [
                $index + 1,
                $credential['username'],
                $credential['name'],
                $credential['password'],
            ];
        }, $this->credentials, array_keys($this->credentials));
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
