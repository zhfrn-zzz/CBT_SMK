<?php

declare(strict_types=1);

namespace App\Http\Requests\Siswa;

use Illuminate\Foundation\Http\FormRequest;

class CheckInAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSiswa();
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.size' => 'Kode presensi harus 6 digit.',
        ];
    }
}
