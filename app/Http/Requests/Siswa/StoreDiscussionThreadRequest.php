<?php

declare(strict_types=1);

namespace App\Http\Requests\Siswa;

use App\Traits\SanitizesHtml;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscussionThreadRequest extends FormRequest
{
    use SanitizesHtml;

    public function authorize(): bool
    {
        return $this->user()->isSiswa();
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

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge(['content' => $this->sanitizeHtml($this->input('content'))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $inClassroom = $this->user()->classrooms()
                ->where('classrooms.id', $this->input('classroom_id'))
                ->exists();

            if (! $inClassroom) {
                $validator->errors()->add('classroom_id', 'Anda tidak terdaftar di kelas ini.');
            }
        });
    }
}
