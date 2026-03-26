<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CredentialController extends Controller
{
    public function downloadPdf(string $key, AuditService $auditService): Response
    {
        $credentials = Cache::get("credentials:{$key}");

        if (! $credentials) {
            abort(404, 'Kredensial sudah expired atau tidak ditemukan. Lakukan import ulang.');
        }

        $pdf = Pdf::loadView('pdf.student-credentials', [
            'credentials' => $credentials,
            'schoolName' => setting('school_name', config('school.name')),
            'logoPath' => setting('logo_path', config('school.logo_path')),
            'date' => now()->format('d F Y'),
        ]);

        $auditService->log(
            'credential_download',
            'credential',
            null,
            ['format' => 'pdf', 'count' => count($credentials)],
            'Download kredensial siswa (PDF)',
        );

        $filename = 'kredensial-siswa-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadExcel(string $key, AuditService $auditService): BinaryFileResponse
    {
        $credentials = Cache::get("credentials:{$key}");

        if (! $credentials) {
            abort(404, 'Kredensial sudah expired atau tidak ditemukan. Lakukan import ulang.');
        }

        $auditService->log(
            'credential_download',
            'credential',
            null,
            ['format' => 'excel', 'count' => count($credentials)],
            'Download kredensial siswa (Excel)',
        );

        $filename = 'kredensial-siswa-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new \App\Exports\CredentialExport($credentials),
            $filename,
        );
    }
}
