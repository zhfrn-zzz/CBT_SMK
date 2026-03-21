<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidMimeType implements ValidationRule
{
    /** @var array<string, list<string>> */
    protected array $extensionMimeMap = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'svg' => ['image/svg+xml'],
        'gif' => ['image/gif'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** @var list<string> */
    protected array $blockedExtensions = [
        'exe', 'bat', 'cmd', 'sh', 'php', 'phar', 'js', 'vbs',
        'wsf', 'msi', 'com', 'scr', 'pif', 'hta', 'cpl', 'ps1',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof \Illuminate\Http\UploadedFile) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (in_array($extension, $this->blockedExtensions, true)) {
            $fail('Tipe file :attribute tidak diizinkan.');

            return;
        }

        $detectedMime = $value->getMimeType();

        if (isset($this->extensionMimeMap[$extension])) {
            if (! in_array($detectedMime, $this->extensionMimeMap[$extension], true)) {
                $fail('Tipe file :attribute tidak sesuai dengan ekstensinya.');
            }
        }
    }
}
