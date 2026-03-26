<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: hsl(210 40% 98%);
            }

            html.dark {
                background-color: hsl(222.2 47.4% 8.2%);
            }
        </style>

        {{-- Dynamic brand colors from admin settings --}}
        @php
            $primaryColor = setting('primary_color', '#2563eb');
            $secondaryColor = setting('secondary_color', '#64748b');
        @endphp
        @if ($primaryColor !== '#2563eb' || $secondaryColor !== '#64748b')
        <style>
            :root {
                --primary: {{ $primaryColor }};
                --ring: {{ $primaryColor }};
                --sidebar-primary: {{ $primaryColor }};
                --sidebar-ring: {{ $primaryColor }};
                --chart-1: {{ $primaryColor }};
            }
            .dark {
                --primary: {{ $primaryColor }};
                --ring: {{ $primaryColor }};
                --sidebar-primary: {{ $primaryColor }};
                --sidebar-ring: {{ $primaryColor }};
                --chart-1: {{ $primaryColor }};
            }
        </style>
        @endif

        <title inertia>{{ setting('app_name', config('app.name', 'Laravel')) }}</title>

        @php
            $faviconPath = setting('logo_small_path');
        @endphp
        @if ($faviconPath)
            <link rel="icon" href="/storage/{{ $faviconPath }}" type="image/png">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @endif
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Self-hosted DM Sans font --}}
        <link rel="preload" href="/fonts/dm-sans/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="/fonts/dm-sans/dm-sans.css">

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
