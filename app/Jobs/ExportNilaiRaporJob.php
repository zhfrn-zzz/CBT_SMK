<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\NilaiRaporExport;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportNilaiRaporJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public function __construct(
        public readonly int $classroomId,
        public readonly int $academicYearId,
        public readonly int $requestedByUserId,
    ) {}

    public function handle(): void
    {
        $classroom = Classroom::findOrFail($this->classroomId);
        $academicYear = AcademicYear::findOrFail($this->academicYearId);
        $requestedBy = User::findOrFail($this->requestedByUserId);

        $filename = 'rapor-'
            .Str::slug($classroom->name).'-'
            .Str::slug($academicYear->name).'-'
            .Str::uuid()
            .'.xlsx';

        $path = 'exports/'.$filename;

        Excel::store(
            new NilaiRaporExport($classroom, $academicYear),
            $path,
            'local'
        );

        $requestedBy->notify(new ExportReadyNotification($filename, $path));
    }
}
