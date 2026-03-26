<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\GuruImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuruImportController extends Controller
{
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $import = new GuruImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = collect($failures)
                ->take(10)
                ->map(fn ($f) => "Baris {$f->row()}: {$f->errors()[0]}")
                ->implode(', ');

            return back()->with('error', "Import gagal: {$errors}");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $count = count($import->getResults());

        return back()->with('success', "{$count} guru berhasil diimport.");
    }

    public function template(): BinaryFileResponse
    {
        $templatePath = resource_path('templates/template-import-guru.xlsx');

        if (! file_exists($templatePath)) {
            // Generate simple template on the fly
            $headers = ['NIP', 'Nama', 'Email', 'Telepon'];
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($headers as $col => $header) {
                $colLetter = chr(65 + $col); // A, B, C, D
                $sheet->setCellValue("{$colLetter}1", $header);
                $sheet->getStyle("{$colLetter}1")->getFont()->setBold(true);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            if (! is_dir(dirname($templatePath))) {
                mkdir(dirname($templatePath), 0755, true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($templatePath);
        }

        return response()->download($templatePath, 'template-import-guru.xlsx');
    }
}
