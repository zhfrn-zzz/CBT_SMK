<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use App\Models\TeachingAssignment;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isGuru();
    }

    public function rules(): array
    {
        return [
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'meeting_date' => ['required', 'date'],
            'meeting_number' => ['required', 'integer', 'min:1'],
            'duration_minutes' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isAssigned = TeachingAssignment::where('user_id', $this->user()->id)
                ->where('subject_id', $this->input('subject_id'))
                ->where('classroom_id', $this->input('classroom_id'))
                ->exists();

            if (! $isAssigned) {
                $validator->errors()->add('classroom_id', 'Anda tidak terdaftar sebagai pengajar untuk kelas ini.');
            }
        });
    }
}
