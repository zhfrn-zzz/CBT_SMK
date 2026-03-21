<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use file cache for rate limiting so it works even when Redis is down
        $this->app->singleton(\Illuminate\Cache\RateLimiter::class, function ($app) {
            return new \Illuminate\Cache\RateLimiter(
                $app->make('cache')->store('file')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->registerAuditListeners();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        // Exam save-answers: max 6 per minute per user (auto-save ~30s interval)
        RateLimiter::for('exam-save', fn (Request $request) => Limit::perMinute(6)->by((string) $request->user()?->id)
        );

        // Exam log-activity: max 30 per minute per user
        RateLimiter::for('exam-activity', fn (Request $request) => Limit::perMinute(30)->by((string) $request->user()?->id)
        );

        // Bulk import: max 3 per minute per user
        RateLimiter::for('bulk-import', fn (Request $request) => Limit::perMinute(3)->by((string) $request->user()?->id)
        );
    }

    protected function registerAuditListeners(): void
    {
        Event::listen(\Illuminate\Auth\Events\Login::class, \App\Listeners\AuditLoginListener::class);
        Event::listen(\Illuminate\Auth\Events\Logout::class, \App\Listeners\AuditLogoutListener::class);
        Event::listen(\Illuminate\Auth\Events\Failed::class, \App\Listeners\AuditFailedLoginListener::class);
    }
}
