<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\GradeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kelas wajib diisi.',
            'academic_year_id.required' => 'Tahun ajaran wajib dipilih.',
            'academic_year_id.exists' => 'Tahun ajaran tidak valid.',
            'department_id.required' => 'Jurusan wajib dipilih.',
            'department_id.exists' => 'Jurusan tidak valid.',
            'grade_level.required' => 'Tingkat kelas wajib dipilih.',
        ];
    }
}
