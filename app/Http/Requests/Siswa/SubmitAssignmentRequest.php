<?php

declare(strict_types=1);

namespace App\Http\Requests\Siswa;

use App\Rules\ValidMimeType;
use App\Traits\SanitizesHtml;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAssignmentRequest extends FormRequest
{
    use SanitizesHtml;

    public function authorize(): bool
    {
        return $this->user()->isSiswa();
    }

    public function rules(): array
    {
        $assignment = $this->route('assignment');
        $type = $assignment?->submission_type?->value ?? 'file_or_text';

        return [
            'content' => ['required_if:type,text', 'nullable', 'string'],
            'file' => [
                'required_if:type,file',
                'nullable',
                'file',
                'min:1',
                'mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,zip,rar',
                'max:25600',
                new ValidMimeType,
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge(['content' => $this->sanitizeHtml($this->input('content'))]);
        }
    }
}
