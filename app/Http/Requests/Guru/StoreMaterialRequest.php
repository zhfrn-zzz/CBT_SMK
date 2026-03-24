<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use App\Models\TeachingAssignment;
use App\Rules\ValidMimeType;
use App\Traits\SanitizesHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
{
    use SanitizesHtml;

    public function authorize(): bool
    {
        return $this->user()->isGuru();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'type' => ['required', Rule::in(['file', 'video_link', 'text'])],
            'file' => ['required_if:type,file', 'nullable', 'file', 'min:1', 'mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,gif', 'max:51200', new ValidMimeType],
            'video_url' => ['required_if:type,video_link', 'nullable', 'url', 'regex:/youtube\.com|youtu\.be/'],
            'text_content' => ['required_if:type,text', 'nullable', 'string'],
            'topic' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('text_content')) {
            $this->merge(['text_content' => $this->sanitizeHtml($this->input('text_content'))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $subjectId = $this->input('subject_id');
            $classroomId = $this->input('classroom_id');

            $isAssigned = TeachingAssignment::where('user_id', $this->user()->id)
                ->where('subject_id', $subjectId)
                ->where('classroom_id', $classroomId)
                ->exists();

            if (! $isAssigned) {
                $validator->errors()->add('classroom_id', 'Anda tidak terdaftar sebagai pengajar untuk kelas dan mata pelajaran ini.');
            }
        });
    }
}
