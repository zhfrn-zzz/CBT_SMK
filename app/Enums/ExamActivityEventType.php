<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamActivityEventType: string
{
    case TabSwitch = 'tab_switch';
    case FullscreenExit = 'fullscreen_exit';
    case FocusLost = 'focus_lost';
    case CopyAttempt = 'copy_attempt';
    case RightClick = 'right_click';
    case ProctorExtendTime = 'proctor_extend_time';
    case ProctorTerminate = 'proctor_terminate';
    case ProctorInvalidateQuestion = 'proctor_invalidate_question';

    public function label(): string
    {
        return match ($this) {
            self::TabSwitch => 'Pindah Tab',
            self::FullscreenExit => 'Keluar Fullscreen',
            self::FocusLost => 'Kehilangan Fokus',
            self::CopyAttempt => 'Percobaan Copy',
            self::RightClick => 'Klik Kanan',
            self::ProctorExtendTime => 'Perpanjangan Waktu (Pengawas)',
            self::ProctorTerminate => 'Terminasi (Pengawas)',
            self::ProctorInvalidateQuestion => 'Pembatalan Soal (Pengawas)',
        };
    }
}
