<?php

declare(strict_types=1);

namespace App\Enums;

enum QuestionType: string
{
    case PilihanGanda = 'pilihan_ganda';
    case BenarSalah = 'benar_salah';
    case Esai = 'esai';
    case IsianSingkat = 'isian_singkat';
    case Menjodohkan = 'menjodohkan';
    case Ordering = 'ordering';
    case MultipleAnswer = 'multiple_answer';

    public function label(): string
    {
        return match ($this) {
            self::PilihanGanda => 'Pilihan Ganda',
            self::BenarSalah => 'Benar/Salah',
            self::Esai => 'Esai',
            self::IsianSingkat => 'Isian Singkat',
            self::Menjodohkan => 'Menjodohkan',
            self::Ordering => 'Pengurutan',
            self::MultipleAnswer => 'Pilihan Ganda Kompleks',
        };
    }
}
