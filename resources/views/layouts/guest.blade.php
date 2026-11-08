<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name', 'HOP') }}</title>
        <meta property="og:title" content="{{ $title ?? config('app.name', 'HOP') }}">
        <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ route('brand.favicon') }}">
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                try {
                    var t = localStorage.getItem('helink-theme');
                    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>
        <x-hop::fonts />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="app-bg min-h-screen">
        <x-session-expired-notice />
        <div class="relative z-10 flex min-h-screen flex-col items-center px-4 py-8 sm:justify-center">
            <div class="mb-6 flex w-full max-w-md items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" title="{{ __('Home') }}">
                    <x-wordmark class="h-10 w-auto" />
                </a>
                {{-- Language and theme, as in every dashboard top bar: the sign-in pages are the first thing a visitor sees. --}}
                <div class="flex items-center gap-1.5">
                    <x-hop::language-switcher />
                    <button type="button" class="icon-btn" aria-label="{{ __('Toggle theme') }}" title="{{ __('Toggle theme') }}"
                        data-toggle-dark>
                        <svg class="h-[18px] w-[18px] dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/></svg>
                        <svg class="hidden h-[18px] w-[18px] dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>
                </div>
            </div>
            <main class="w-full max-w-md">
                <div class="auth-card glass-strong w-full max-w-md rounded-3xl border-t-4 border-t-sky-400/70 p-6 text-ink shadow-xl shadow-sky-900/5 sm:p-8">
                    {{ $slot }}
                </div>
            </main>
            <p class="mt-6 flex flex-wrap items-center justify-center gap-x-1 text-xs text-muted">
                <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center px-1.5 hover:underline">{{ __('Terms') }}</a> · <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center px-1.5 hover:underline">{{ __('Privacy') }}</a> · <a href="{{ route('help') }}" class="inline-flex min-h-11 items-center px-1.5 hover:underline">{{ __('Help') }}</a>
            </p>
        </div>
    </body>
</html>
