<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['boolean'],
        ];

        if ($this->isMethod('POST')) {
            $rules['password'] = ['required', 'string', Password::defaults()];
        } else {
            $rules['password'] = ['nullable', 'string', Password::defaults()];
        }

        // Siswa: optional classroom assignment
        $rules['classroom_id'] = ['nullable', 'integer', 'exists:classrooms,id'];

        // Guru: optional teaching assignments
        $rules['teachings'] = ['nullable', 'array'];
        $rules['teachings.*.classroom_id'] = ['required_with:teachings', 'integer', 'exists:classrooms,id'];
        $rules['teachings.*.subject_id'] = ['required_with:teachings', 'integer', 'exists:subjects,id'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'role.required' => 'Role wajib dipilih.',
        ];
    }
}
