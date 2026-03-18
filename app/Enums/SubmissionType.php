<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionType: string
{
    case File = 'file';
    case Text = 'text';
    case FileOrText = 'file_or_text';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Upload File',
            self::Text => 'Teks',
            self::FileOrText => 'File atau Teks',
        };
    }
}
