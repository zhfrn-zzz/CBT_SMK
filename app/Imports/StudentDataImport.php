<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentDataImport implements ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /** @var array<int, array{name: string, username: string}> */
    private array $results = [];

    /** @var array<int, array{row: int, errors: string[]}> */
    private array $errors = [];

    public function collection(Collection $rows): void
    {
        $activeYear = AcademicYear::active()->first();

        $departments = Department::all()->keyBy(fn ($d) => Str::lower($d->code));
        $classrooms = $activeYear
            ? Classroom::where('academic_year_id', $activeYear->id)->get()
            : collect();

        foreach ($rows as $row) {
            $username = (string) ($row['nis'] ?? $row['username'] ?? '');
            $name = (string) ($row['nama'] ?? $row['name'] ?? '');

            if (empty($username) || empty($name)) {
                continue;
            }

            if (User::where('username', $username)->exists()) {
                continue;
            }

            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => ! empty($row['email']) ? (string) $row['email'] : null,
                'password' => Hash::make($username),
                'role' => UserRole::Siswa,
                'is_active' => true,
            ]);

            if (! empty($row['jurusan']) && ! empty($row['kelas']) && $activeYear) {
                $dept = $departments->get(Str::lower((string) $row['jurusan']));
                if ($dept) {
                    $classroom = $classrooms
                        ->where('department_id', $dept->id)
                        ->where('name', (string) $row['kelas'])
                        ->first();

                    if ($classroom) {
                        $user->classrooms()->syncWithoutDetaching([$classroom->id]);
                    }
                }
            }

            $this->results[] = ['name' => $user->name, 'username' => $user->username];
        }
    }

    public function rules(): array
    {
        return [];
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function batchSize(): int
    {
        return 200;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
