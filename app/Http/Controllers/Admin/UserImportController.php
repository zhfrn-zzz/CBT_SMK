<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\StudentImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class UserImportController extends Controller
{
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'File wajib diunggah.',
            'file.mimes' => 'Format file harus Excel (xlsx, xls) atau CSV.',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ]);

        try {
            $import = new StudentImport();
            Excel::import($import, $request->file('file'));

            $results = $import->getResults();
            $count = count($results);

            // Store credentials in cache for download (TTL 30 minutes)
            $credentialKey = Str::uuid()->toString();
            Cache::put("credentials:{$credentialKey}", $results, 1800);

            return redirect()->route('admin.users.index')
                ->with('success', "{$count} siswa berhasil diimport.")
                ->with('credential_key', $credentialKey)
                ->with('credential_count', $count);
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $sanitizedErrors = array_map(fn ($err) => htmlspecialchars($err, ENT_QUOTES, 'UTF-8'), $failure->errors());
                $errors[] = "Baris {$failure->row()}: ".implode(', ', $sanitizedErrors);
            }

            return back()->with('error', implode("\n", $errors));
        }
    }
}
