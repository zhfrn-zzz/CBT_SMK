<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use App\Traits\SanitizesHtml;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    use SanitizesHtml;

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
            'is_public' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge(['content' => $this->sanitizeHtml($this->input('content'))]);
        }
    }
}
