<?php

declare(strict_types=1);

namespace App\Enums;

enum MaterialType: string
{
    case File = 'file';
    case VideoLink = 'video_link';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::File => 'File Upload',
            self::VideoLink => 'Link Video YouTube',
            self::Text => 'Teks / Artikel',
        };
    }
}
