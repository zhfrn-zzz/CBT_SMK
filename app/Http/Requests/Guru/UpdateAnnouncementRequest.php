<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isGuru() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'is_pinned' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
