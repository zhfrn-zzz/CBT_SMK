<?php

declare(strict_types=1);

namespace App\Http\Requests\Siswa;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssignmentRequest extends FormRequest
{
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
                'mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,zip,rar',
                'max:25600',
            ],
        ];
    }
}
