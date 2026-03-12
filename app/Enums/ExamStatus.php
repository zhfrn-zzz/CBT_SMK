<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Scheduled => 'Dijadwalkan',
            self::Active => 'Berlangsung',
            self::Completed => 'Selesai',
            self::Archived => 'Diarsipkan',
        };
    }
}
