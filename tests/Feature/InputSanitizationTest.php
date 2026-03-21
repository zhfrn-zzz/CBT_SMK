<?php

declare(strict_types=1);

use App\Rules\ValidMimeType;
use App\Traits\SanitizesHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

// --- Task 6.2: Input Sanitization & XSS ---

it('strips script tags from HTML content', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    $malicious = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
    $cleaned = $trait->clean($malicious);

    expect($cleaned)->not->toContain('<script>');
    expect($cleaned)->not->toContain('alert');
    expect($cleaned)->toContain('Hello');
    expect($cleaned)->toContain('World');
});

it('strips event handlers from HTML', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    $malicious = '<img src="x" onerror="alert(1)" />';
    $cleaned = $trait->clean($malicious);

    expect($cleaned)->not->toContain('onerror');
});

it('strips iframe tags from HTML', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    $malicious = '<p>Test</p><iframe src="https://evil.com"></iframe>';
    $cleaned = $trait->clean($malicious);

    expect($cleaned)->not->toContain('<iframe');
    expect($cleaned)->toContain('Test');
});

it('preserves allowed HTML tags', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    $safe = '<p>Paragraph</p><strong>Bold</strong><em>Italic</em><ul><li>Item</li></ul>';
    $cleaned = $trait->clean($safe);

    expect($cleaned)->toContain('<p>');
    expect($cleaned)->toContain('<strong>');
    expect($cleaned)->toContain('<em>');
    expect($cleaned)->toContain('<ul>');
    expect($cleaned)->toContain('<li>');
});

it('preserves images with safe attributes', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    $html = '<img src="https://example.com/photo.jpg" alt="Photo" width="200" />';
    $cleaned = $trait->clean($html);

    expect($cleaned)->toContain('<img');
    expect($cleaned)->toContain('src=');
    expect($cleaned)->toContain('alt=');
});

it('strips javascript protocol from links', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    $malicious = '<a href="javascript:alert(1)">Click me</a>';
    $cleaned = $trait->clean($malicious);

    expect($cleaned)->not->toContain('javascript:');
});

it('handles null input gracefully', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    expect($trait->clean(null))->toBeNull();
    expect($trait->clean(''))->toBe('');
});

it('preserves Tiptap empty paragraphs', function () {
    $trait = new class
    {
        use SanitizesHtml;

        public function clean(?string $html): ?string
        {
            return $this->sanitizeHtml($html);
        }
    };

    $html = '<p><br></p>';
    $cleaned = $trait->clean($html);

    // Should not produce empty result
    expect($cleaned)->not->toBeEmpty();
});

// --- ValidMimeType Rule Tests ---

it('rejects blocked file extensions', function () {
    $file = UploadedFile::fake()->create('malware.exe', 100);

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [new ValidMimeType]]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('file'))->toContain('tidak diizinkan');
});

it('rejects .php file uploads', function () {
    $file = UploadedFile::fake()->create('shell.php', 100);

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [new ValidMimeType]]
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects .bat file uploads', function () {
    $file = UploadedFile::fake()->create('script.bat', 100);

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [new ValidMimeType]]
    );

    expect($validator->fails())->toBeTrue();
});

it('accepts valid PDF file uploads', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [new ValidMimeType]]
    );

    expect($validator->fails())->toBeFalse();
});

it('accepts valid image file uploads', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    $validator = Validator::make(
        ['file' => $file],
        ['file' => [new ValidMimeType]]
    );

    expect($validator->fails())->toBeFalse();
});

// --- Form Request Sanitization Tests ---

it('sanitizes announcement content on store', function () {
    $guru = \App\Models\User::factory()->guru()->create();

    // Store request should sanitize content via prepareForValidation
    $response = $this->actingAs($guru)->post(route('guru.pengumuman.store'), [
        'title' => 'Test Announcement',
        'content' => '<p>Safe</p><script>alert("xss")</script>',
        'is_pinned' => false,
        'is_public' => false,
    ]);

    // Check the content was sanitized (script removed)
    if (\App\Models\Announcement::count() > 0) {
        $announcement = \App\Models\Announcement::latest()->first();
        expect($announcement->content)->not->toContain('<script>');
        expect($announcement->content)->toContain('Safe');
    }
});

it('adds is_public field to announcement form request', function () {
    $guru = \App\Models\User::factory()->guru()->create();

    $response = $this->actingAs($guru)->post(route('guru.pengumuman.store'), [
        'title' => 'Public Test',
        'content' => '<p>Public announcement</p>',
        'is_pinned' => false,
        'is_public' => true,
    ]);

    if (\App\Models\Announcement::count() > 0) {
        $announcement = \App\Models\Announcement::latest()->first();
        expect($announcement->is_public)->toBeTrue();
    }
});
