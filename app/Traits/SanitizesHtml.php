<?php

declare(strict_types=1);

namespace App\Traits;

use Mews\Purifier\Facades\Purifier;

trait SanitizesHtml
{
    protected function sanitizeHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return Purifier::clean($html);
    }

    /**
     * Sanitize plain text (no auto paragraph wrapping).
     * Used for short text fields like matching pairs, options, etc.
     */
    protected function sanitizePlainText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        return strip_tags($text);
    }
}
