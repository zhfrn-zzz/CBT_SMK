<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Sedang Mengerjakan',
            self::Submitted => 'Sudah Dikumpulkan',
            self::Graded => 'Sudah Dinilai',
        };
    }
}
