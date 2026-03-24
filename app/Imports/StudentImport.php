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
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentImport implements ToCollection, WithHeadingRow, WithValidation
{
    private array $results = [];

    private array $seenNis = [];

    public function collection(Collection $rows): void
    {
        // Pre-validate: check for within-file duplicate NIS
        $nisList = $rows->pluck('nis')->filter()->map(fn ($v) => (string) $v);
        $duplicates = $nisList->duplicates();
        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'NIS duplikat ditemukan dalam file: ' . $duplicates->unique()->implode(', ')
            );
        }

        $activeYear = AcademicYear::active()->first();

        // Pre-load departments and classrooms for lookup
        $departments = Department::all()->keyBy(fn ($d) => Str::lower($d->code));
        $classrooms = $activeYear
            ? Classroom::where('academic_year_id', $activeYear->id)->get()
            : collect();

        foreach ($rows as $row) {
            $password = $row['password'] ?? Str::random(8);

            $user = User::create([
                'name' => $row['nama'],
                'username' => (string) $row['nis'],
                'email' => $row['email'] ?? null,
                'password' => Hash::make((string) $password),
                'role' => UserRole::Siswa,
                'is_active' => true,
            ]);

            // Auto-assign to classroom if jurusan & kelas provided
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

            $this->results[] = [
                'name' => $user->name,
                'username' => $user->username,
                'password' => (string) $password,
            ];
        }
    }

    public function rules(): array
    {
        return [
            'nis' => ['required', 'unique:users,username'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'password' => ['nullable', 'string'],
            'jurusan' => ['nullable', 'string'],
            'kelas' => ['nullable', 'string'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS :input sudah terdaftar.',
            'nama.required' => 'Nama wajib diisi.',
        ];
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
