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
    case ScreenshotAttempt = 'screenshot_attempt';
    case DevtoolsAttempt = 'devtools_attempt';
    case DevtoolsOpen = 'devtools_open';
    case PrintAttempt = 'print_attempt';
    case CopyPasteAttempt = 'copy_paste_attempt';
    case KeyboardShortcut = 'keyboard_shortcut';

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
            self::ScreenshotAttempt => 'Percobaan Screenshot',
            self::DevtoolsAttempt => 'Percobaan Buka DevTools',
            self::DevtoolsOpen => 'DevTools Terdeteksi Terbuka',
            self::PrintAttempt => 'Percobaan Print',
            self::CopyPasteAttempt => 'Percobaan Copy/Paste',
            self::KeyboardShortcut => 'Shortcut Keyboard Terblokir',
        };
    }
}
