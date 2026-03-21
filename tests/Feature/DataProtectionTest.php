<?php

declare(strict_types=1);

use App\Models\User;

// --- Task 6.5: Data Protection ---

it('adds security headers to all responses', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-XSS-Protection', '1; mode=block');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('adds security headers to authenticated responses', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('does not add CSP header in non-production environment', function () {
    // Default testing environment is not 'production'
    $response = $this->get('/');

    $response->assertHeaderMissing('Content-Security-Policy');
});

it('forces HTTPS in production', function () {
    // Verify the AppServiceProvider boot method has the forceScheme logic
    // We test this by confirming production check exists
    expect(app()->environment('production'))->toBeFalse();
    expect(url('/'))->not->toStartWith('https://');

    // In a separate check, verify the code path exists
    $providerFile = file_get_contents(app_path('Providers/AppServiceProvider.php'));
    expect($providerFile)->toContain("URL::forceScheme('https')");
    expect($providerFile)->toContain("environment('production')");
});

it('has CORS config with allowed origins', function () {
    $corsConfig = config('cors');

    expect($corsConfig)->not->toBeNull();
    expect($corsConfig['paths'])->toContain('api/*');
    expect($corsConfig['supports_credentials'])->toBeTrue();
    expect($corsConfig['allowed_origins'])->toBeArray();
});

it('hides sensitive fields from User model serialization', function () {
    $user = User::factory()->create();
    $serialized = $user->toArray();

    expect($serialized)->not->toHaveKey('password');
    expect($serialized)->not->toHaveKey('remember_token');
    expect($serialized)->not->toHaveKey('two_factor_secret');
    expect($serialized)->not->toHaveKey('two_factor_recovery_codes');
});

it('does not expose sensitive user fields via Inertia shared data', function () {
    $user = User::factory()->siswa()->create();

    $response = $this->actingAs($user)->get('/siswa/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('auth.user.id', $user->id)
        ->where('auth.user.name', $user->name)
        ->missing('auth.user.password')
        ->missing('auth.user.remember_token')
        ->missing('auth.user.two_factor_secret')
        ->missing('auth.user.two_factor_recovery_codes')
    );
});

it('has SecurityHeaders middleware registered globally', function () {
    $middlewareGroups = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups();

    // Check that SecurityHeaders is in the web middleware stack
    // by making a request and verifying headers are present
    $response = $this->get('/');
    $response->assertHeader('X-Content-Type-Options');
});

it('blocks dangerous URIs in raw SQL audit (no user input in DB::raw)', function () {
    // Verify that all DB::raw usages are safe aggregate functions
    // This is a static check - we read the files and verify patterns
    $analyticsPath = app_path('Services/Analytics/AnalyticsService.php');
    if (file_exists($analyticsPath)) {
        $content = file_get_contents($analyticsPath);

        // All DB::raw usages should be aggregate functions, not user input
        preg_match_all('/DB::raw\([\'"](.+?)[\'"]\)/', $content, $matches);

        foreach ($matches[1] as $rawSql) {
            // Should only contain safe aggregate functions
            expect($rawSql)->toMatch('/^(AVG|COUNT|MAX|MIN|MONTH|CAST|DISTINCT|SUM)\b/i');
        }
    }
});
