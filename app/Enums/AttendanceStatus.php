<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alfa = 'alfa';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alfa => 'Alfa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hadir => 'green',
            self::Izin => 'blue',
            self::Sakit => 'yellow',
            self::Alfa => 'red',
        };
    }
}
