<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => fn () => setting('app_name', config('app.name')),
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->toArray(),
                    ['avatar' => $request->user()->photo_url]
                ) : null,
                'unread_notifications_count' => $request->user()
                    ? Cache::remember(
                        "user:{$request->user()->id}:unread_notifications",
                        60,
                        fn () => $request->user()->unreadNotifications()->count()
                    )
                    : 0,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'generated_password' => fn () => $request->session()->get('generated_password'),
                'generated_user_name' => fn () => $request->session()->get('generated_user_name'),
                'generated_user_username' => fn () => $request->session()->get('generated_user_username'),
                'credential_key' => fn () => $request->session()->get('credential_key'),
                'credential_count' => fn () => $request->session()->get('credential_count'),
            ],
            'app_settings' => fn () => [
                'app_name' => setting('app_name', config('app.name')),
                'school_name' => setting('school_name', ''),
                'logo_path' => setting('logo_path', ''),
                'logo_small_path' => setting('logo_small_path', ''),
                'primary_color' => setting('primary_color', '#2563eb'),
                'secondary_color' => setting('secondary_color', '#64748b'),
                'footer_text' => setting('footer_text', ''),
                'show_powered_by' => setting('show_powered_by', true),
            ],
        ];
    }
}
