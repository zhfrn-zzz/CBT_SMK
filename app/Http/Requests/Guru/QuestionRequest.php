<?php

declare(strict_types=1);

namespace App\Http\Requests\Guru;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'content' => ['required', 'string'],
            'points' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'explanation' => ['nullable', 'string'],
        ];

        // Options required for PG and Benar/Salah
        if (in_array($type, ['pilihan_ganda', 'benar_salah', 'multiple_answer'])) {
            $rules['options'] = ['required', 'array', 'min:2'];
            $rules['options.*.label'] = ['required', 'string', 'max:10'];
            $rules['options.*.content'] = ['required', 'string'];
            $rules['options.*.is_correct'] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Tipe soal wajib dipilih.',
            'content.required' => 'Konten soal wajib diisi.',
            'points.required' => 'Bobot nilai wajib diisi.',
            'points.min' => 'Bobot nilai minimal 0.01.',
            'options.required' => 'Pilihan jawaban wajib diisi.',
            'options.min' => 'Minimal 2 pilihan jawaban.',
            'options.*.content.required' => 'Konten pilihan jawaban wajib diisi.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $options = $this->input('options', []);

            if (in_array($type, ['pilihan_ganda', 'benar_salah'])) {
                $correctCount = collect($options)->where('is_correct', true)->count();
                if ($correctCount !== 1) {
                    $validator->errors()->add('options', 'Harus ada tepat 1 jawaban yang benar.');
                }
            }

            if ($type === 'multiple_answer') {
                $correctCount = collect($options)->where('is_correct', true)->count();
                if ($correctCount < 1) {
                    $validator->errors()->add('options', 'Minimal 1 jawaban yang benar.');
                }
            }
        });
    }
}
