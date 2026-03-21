<?php

declare(strict_types=1);

use App\Models\User;

// --- Task 6.1: Rate Limiting ---

it('applies rate limit to exam save-answers route', function () {
    $siswa = User::factory()->siswa()->create();
    $this->actingAs($siswa);

    // We can't easily test the actual rate limit without making many requests,
    // but we can verify the route has the throttle middleware applied
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'siswa.ujian.save-answers');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('throttle:exam-save');
});

it('applies rate limit to exam log-activity route', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'api.exam.log-activity');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('throttle:exam-activity');
});

it('applies rate limit to admin user import route', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'admin.users.import');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('throttle:bulk-import');
});

it('applies rate limit to admin data exchange import route', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'admin.data-exchange.import');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('throttle:bulk-import');
});

it('applies rate limit to guru question import route', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'guru.bank-soal.soal.import');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('throttle:bulk-import');
});

it('has exam-save rate limiter configured', function () {
    // Verify the rate limiter is registered
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('exam-save');
    expect($limiter)->toBeInstanceOf(Closure::class);
});

it('has exam-activity rate limiter configured', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('exam-activity');
    expect($limiter)->toBeInstanceOf(Closure::class);
});

it('has bulk-import rate limiter configured', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('bulk-import');
    expect($limiter)->toBeInstanceOf(Closure::class);
});
