<?php

declare(strict_types=1);

namespace App\Enums;

enum GradeLevel: string
{
    case X = '10';
    case XI = '11';
    case XII = '12';

    public function label(): string
    {
        return match ($this) {
            self::X => 'Kelas 10',
            self::XI => 'Kelas 11',
            self::XII => 'Kelas 12',
        };
    }
}
