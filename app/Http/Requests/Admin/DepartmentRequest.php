<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('department')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($departmentId)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama jurusan wajib diisi.',
            'code.required' => 'Kode jurusan wajib diisi.',
            'code.unique' => 'Kode jurusan sudah digunakan.',
        ];
    }
}
