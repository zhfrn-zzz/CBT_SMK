<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\QuestionType;
use App\Models\QuestionBank;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class QuestionImport implements ToCollection, WithHeadingRow
{
    private array $results = [];

    private array $errors = [];

    private int $imported = 0;

    public function __construct(
        private readonly QuestionBank $questionBank,
    ) {}

    public function collection(Collection $rows): void
    {
        $maxOrder = $this->questionBank->questions()->max('order') ?? 0;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +2 because heading row is 1, 0-indexed

            $type = $this->resolveType((string) ($row['tipe'] ?? ''));
            if ($type === null) {
                $this->errors[] = "Baris {$rowNum}: Tipe soal tidak valid '{$row['tipe']}'.";

                continue;
            }

            $content = trim((string) ($row['soal'] ?? ''));
            if ($content === '') {
                $this->errors[] = "Baris {$rowNum}: Konten soal kosong.";

                continue;
            }

            $points = (float) ($row['bobot'] ?? 1);
            $explanation = trim((string) ($row['pembahasan'] ?? ''));

            $maxOrder++;
            $question = $this->questionBank->questions()->create([
                'type' => $type,
                'content' => "<p>{$content}</p>",
                'points' => $points > 0 ? $points : 1,
                'explanation' => $explanation !== '' ? $explanation : null,
                'order' => $maxOrder,
            ]);

            // For PG and Benar/Salah, create options
            if (in_array($type, [QuestionType::PilihanGanda, QuestionType::BenarSalah])) {
                $correctAnswer = strtoupper(trim((string) ($row['jawaban_benar'] ?? '')));
                $labels = ['A', 'B', 'C', 'D', 'E'];
                $optionKeys = ['opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e'];
                $hasCorrect = false;

                foreach ($optionKeys as $i => $key) {
                    $optionContent = trim((string) ($row[$key] ?? ''));
                    if ($optionContent === '') {
                        continue;
                    }

                    $isCorrect = $labels[$i] === $correctAnswer;
                    if ($isCorrect) {
                        $hasCorrect = true;
                    }

                    $question->options()->create([
                        'label' => $labels[$i],
                        'content' => $optionContent,
                        'is_correct' => $isCorrect,
                        'order' => $i,
                    ]);
                }

                if (! $hasCorrect) {
                    $this->errors[] = "Baris {$rowNum}: Jawaban benar '{$correctAnswer}' tidak sesuai dengan opsi yang ada.";
                }
            }

            $this->imported++;
            $this->results[] = [
                'row' => $rowNum,
                'content' => mb_substr($content, 0, 50),
                'type' => $type->value,
            ];
        }
    }

    private function resolveType(string $value): ?QuestionType
    {
        $value = strtolower(trim($value));

        $map = [
            'pg' => QuestionType::PilihanGanda,
            'pilihan_ganda' => QuestionType::PilihanGanda,
            'pilihan ganda' => QuestionType::PilihanGanda,
            'bs' => QuestionType::BenarSalah,
            'benar_salah' => QuestionType::BenarSalah,
            'benar/salah' => QuestionType::BenarSalah,
            'esai' => QuestionType::Esai,
            'essay' => QuestionType::Esai,
        ];

        return $map[$value] ?? null;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }
}
