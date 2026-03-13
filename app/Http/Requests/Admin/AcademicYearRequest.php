<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Semester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'semester' => ['required', Rule::enum(Semester::class)],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama tahun ajaran wajib diisi.',
            'semester.required' => 'Semester wajib dipilih.',
            'starts_at.required' => 'Tanggal mulai wajib diisi.',
            'ends_at.required' => 'Tanggal selesai wajib diisi.',
            'ends_at.after' => 'Tanggal selesai harus setelah tanggal mulai.',
        ];
    }
}
