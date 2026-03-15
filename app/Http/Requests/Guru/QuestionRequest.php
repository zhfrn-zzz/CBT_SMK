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

        // Options required for PG, Benar/Salah, Multiple Answer, and Ordering
        if (in_array($type, ['pilihan_ganda', 'benar_salah', 'multiple_answer', 'ordering'])) {
            $rules['options'] = ['required', 'array', 'min:2'];
            $rules['options.*.label'] = ['required', 'string', 'max:10'];
            $rules['options.*.content'] = ['required', 'string'];
            $rules['options.*.is_correct'] = ['required', 'boolean'];
        }

        // Keywords required for Isian Singkat
        if ($type === 'isian_singkat') {
            $rules['keywords'] = ['required', 'array', 'min:1'];
            $rules['keywords.*'] = ['required', 'string', 'max:255'];
        }

        // Matching pairs required for Menjodohkan
        if ($type === 'menjodohkan') {
            $rules['matching_pairs'] = ['required', 'array', 'min:2'];
            $rules['matching_pairs.*.premise'] = ['required', 'string'];
            $rules['matching_pairs.*.response'] = ['required', 'string'];
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
            'keywords.required' => 'Kata kunci jawaban wajib diisi.',
            'keywords.min' => 'Minimal 1 kata kunci jawaban.',
            'keywords.*.required' => 'Kata kunci tidak boleh kosong.',
            'matching_pairs.required' => 'Pasangan soal wajib diisi.',
            'matching_pairs.min' => 'Minimal 2 pasangan.',
            'matching_pairs.*.premise.required' => 'Pernyataan (premise) wajib diisi.',
            'matching_pairs.*.response.required' => 'Jawaban pasangan wajib diisi.',
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
