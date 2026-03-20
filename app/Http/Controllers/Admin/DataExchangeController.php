<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\StudentDataExport;
use App\Http\Controllers\Controller;
use App\Imports\StudentDataImport;
use App\Jobs\ExportNilaiRaporJob;
use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExchangeController extends Controller
{
    public function index(Request $request): Response
    {
        $classrooms = Classroom::with(['department', 'academicYear'])->orderBy('name')->get();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();

        return Inertia::render('Admin/DataExchange/Index', [
            'classrooms' => $classrooms,
            'academicYears' => $academicYears,
        ]);
    }

    public function exportStudents(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $classroomId = $request->integer('classroom_id') ?: null;
        $academicYearId = $request->integer('academic_year_id') ?: null;

        $classroom = $classroomId ? Classroom::findOrFail($classroomId) : null;
        $academicYear = $academicYearId ? AcademicYear::findOrFail($academicYearId) : null;

        $filename = 'data-siswa-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(
            new StudentDataExport($classroom, $academicYear),
            $filename
        );
    }

    public function downloadTemplate(): \Illuminate\Http\Response|StreamedResponse
    {
        $headers = ['NIS', 'Nama', 'Email', 'Jurusan', 'Kelas'];
        $csv = implode(',', $headers)."\n";
        $csv .= "12345,Nama Siswa,email@contoh.com,RPL,XI RPL 1\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'template-import-siswa.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importStudents(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new StudentDataImport;
        Excel::import($import, $request->file('file'));

        $count = count($import->getResults());

        return back()->with('success', "{$count} siswa berhasil diimport.");
    }

    public function exportRapor(Request $request): RedirectResponse
    {
        $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        ExportNilaiRaporJob::dispatch(
            $request->integer('classroom_id'),
            $request->integer('academic_year_id'),
            $request->user()->id,
        );

        return back()->with('success', 'Export rapor sedang diproses. Anda akan mendapat notifikasi saat selesai.');
    }

    public function downloadExport(string $filename): StreamedResponse
    {
        $path = 'exports/'.$filename;

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('local')->download($path, $filename);
    }
}
