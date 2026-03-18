<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use App\Models\TeachingAssignment;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscussionThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isGuru();
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
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
