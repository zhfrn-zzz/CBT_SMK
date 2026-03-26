<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SettingService;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Apply SMTP settings from database to Laravel mail config.
     * Only overrides config when admin has configured SMTP via settings page.
     */
    public function boot(): void
    {
        // Defer until mail config is actually needed to avoid DB queries on every request
        $this->app->resolving('mail.manager', function () {
            $this->applySmtpConfig();
        });
    }

    private function applySmtpConfig(): void
    {
        try {
            $settings = app(SettingService::class);

            $host = $settings->get('smtp_host');
            if (empty($host)) {
                return;
            }

            $encryption = $settings->get('smtp_encryption', 'tls');

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) $settings->get('smtp_port', 587),
                'mail.mailers.smtp.username' => $settings->get('smtp_username'),
                'mail.mailers.smtp.password' => $settings->get('smtp_password'),
                'mail.mailers.smtp.scheme' => $encryption === 'none' ? null : $encryption,
            ]);

            $fromAddress = $settings->get('smtp_from_address');
            if (! empty($fromAddress)) {
                config([
                    'mail.from.address' => $fromAddress,
                    'mail.from.name' => $settings->get('smtp_from_name') ?: config('app.name'),
                ]);
            }
        } catch (\Exception) {
            // DB not available (migration pending, etc.) — use .env defaults
        }
    }
}
