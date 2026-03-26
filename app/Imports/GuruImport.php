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

class GuruImport implements ToCollection, WithHeadingRow, WithValidation
{
    private array $results = [];

    public function collection(Collection $rows): void
    {
        $nipList = $rows->pluck('nip')->filter()->map(fn ($v) => (string) $v);
        $duplicates = $nipList->duplicates();
        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'NIP duplikat ditemukan dalam file: ' . $duplicates->unique()->implode(', ')
            );
        }

        foreach ($rows as $row) {
            $password = Str::random(8);

            $user = User::create([
                'name' => $row['nama'],
                'username' => (string) $row['nip'],
                'email' => $row['email'] ?? null,
                'phone' => isset($row['telepon']) ? (string) $row['telepon'] : null,
                'password' => Hash::make($password),
                'role' => UserRole::Guru,
                'is_active' => true,
            ]);

            $this->results[] = [
                'name' => $user->name,
                'username' => $user->username,
                'password' => $password,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nip' => ['required', 'unique:users,username'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'telepon' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function customValidationMessages(): array
    {
        return [
            'nip.required' => 'NIP wajib diisi.',
            'nip.unique' => 'NIP :input sudah terdaftar.',
            'nama.required' => 'Nama wajib diisi.',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
