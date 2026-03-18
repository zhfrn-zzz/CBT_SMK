<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use App\Models\TeachingAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isGuru();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'file' => ['nullable', 'file', 'mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,gif,zip,rar', 'max:51200'],
            'deadline_at' => ['required', 'date', 'after:now'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'allow_late_submission' => ['boolean'],
            'late_penalty_percent' => ['required_if:allow_late_submission,true', 'integer', 'min:0', 'max:100'],
            'submission_type' => ['required', Rule::in(['file', 'text', 'file_or_text'])],
            'is_published' => ['boolean'],
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
                $validator->errors()->add('classroom_id', 'Anda tidak terdaftar sebagai pengajar untuk kelas dan mata pelajaran ini.');
            }
        });
    }
}
