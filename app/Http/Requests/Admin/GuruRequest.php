<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('guru')?->id ?? null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'teachings' => ['nullable', 'array'],
            'teachings.*.classroom_id' => ['required_with:teachings', 'integer', 'exists:classrooms,id'],
            'teachings.*.subject_id' => ['required_with:teachings', 'integer', 'exists:subjects,id'],
        ];

        if ($this->isMethod('POST')) {
            $rules['password'] = ['nullable', 'string', Password::defaults()];
        } else {
            $rules['password'] = ['nullable', 'string', Password::defaults()];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'NIP wajib diisi.',
            'username.unique' => 'NIP sudah digunakan.',
            'email.unique' => 'Email sudah digunakan.',
            'teachings.*.classroom_id.exists' => 'Kelas tidak valid.',
            'teachings.*.subject_id.exists' => 'Mata pelajaran tidak valid.',
        ];
    }
}
