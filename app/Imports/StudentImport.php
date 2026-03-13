<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\UserRole;
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

    public function collection(Collection $rows): void
    {
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
