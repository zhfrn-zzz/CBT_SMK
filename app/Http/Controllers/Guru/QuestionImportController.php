<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Exports\QuestionTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\QuestionImport;
use App\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return Excel::download(new QuestionTemplateExport(), 'template_soal.xlsx');
    }

    public function import(Request $request, QuestionBank $bankSoal): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'File wajib diunggah.',
            'file.mimes' => 'Format file harus Excel (xlsx, xls) atau CSV.',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ]);

        $import = new QuestionImport($bankSoal);
        Excel::import($import, $request->file('file'));

        $imported = $import->getImportedCount();
        $errors = $import->getErrors();

        if (count($errors) > 0) {
            $errorMsg = implode("\n", $errors);

            if ($imported > 0) {
                return redirect()->route('guru.bank-soal.show', $bankSoal)
                    ->with('success', "{$imported} soal berhasil diimport.")
                    ->with('error', "Beberapa baris gagal:\n{$errorMsg}");
            }

            return back()->with('error', "Import gagal:\n{$errorMsg}");
        }

        return redirect()->route('guru.bank-soal.show', $bankSoal)
            ->with('success', "{$imported} soal berhasil diimport.");
    }
}
