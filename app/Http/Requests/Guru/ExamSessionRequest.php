<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use App\Enums\ExamStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'question_bank_id' => ['required', 'exists:question_banks,id'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_randomize_questions' => ['boolean'],
            'is_randomize_options' => ['boolean'],
            'pool_count' => ['nullable', 'integer', 'min:1'],
            'kkm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_tab_switches' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(ExamStatus::class)],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['exists:classrooms,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama ujian wajib diisi.',
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'academic_year_id.required' => 'Tahun ajaran wajib dipilih.',
            'question_bank_id.required' => 'Bank soal wajib dipilih.',
            'duration_minutes.required' => 'Durasi ujian wajib diisi.',
            'starts_at.required' => 'Waktu mulai wajib diisi.',
            'ends_at.required' => 'Waktu selesai wajib diisi.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'classroom_ids.required' => 'Kelas peserta wajib dipilih.',
            'classroom_ids.min' => 'Minimal pilih 1 kelas peserta.',
        ];
    }
}
