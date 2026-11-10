<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ trim(($pageTitle ?? '') ?: config('app.name').' - Hop Easy Link') }}</title>
        <meta name="description" content="{{ __('Your HOP ID: one account for LinkHop, BioHop, PixelHop and every HOP app.') }}">
        <link rel="icon" href="{{ route('brand.favicon') }}">
        {{-- Apply the saved theme before first paint (no light flash in dark mode) --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                try {
                    var t = localStorage.getItem('helink-theme');
                    if (/(?:^|; )hop_ui=clean(?:;|$)/.test(document.cookie)) document.documentElement.dataset.ui = 'clean';
                    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>
        <x-hop::fonts />
        @include('partials.hop-ui')
        @stack('scripts-head')
        @include('partials.hop-bento')
        @vite(['resources/css/app.css', 'resources/css/coss.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="app-bg hub-chrome min-h-screen">
        <x-session-expired-notice />
        @auth
            @php
                $user = auth()->user();
                $sectionTitle = trim(\Illuminate\Support\Str::beforeLast($pageTitle ?? '', ' · ')) ?: 'HOP';
            @endphp
            {{-- The drawer is modal on phones: Escape closes it, the page behind does not scroll while it is open,
                 and growing past the lg breakpoint (where the sidebar is static) drops the state. --}}
            <div x-data="{ nav: false }" @keydown.escape.window="nav = false"
                 @resize.window="if (nav && window.innerWidth >= 1024) nav = false"
                 x-effect="document.documentElement.classList.toggle('overflow-hidden', nav && window.innerWidth < 1024)"
                 class="lg:pl-68">
                <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-xs lg:hidden"></div>
                <aside class="fixed inset-y-0 left-0 z-50 w-68 p-3 transition-transform duration-300 lg:translate-x-0"
                       :class="nav ? 'translate-x-0' : '-translate-x-full'"
                       @click="if ($event.target.closest('a')) nav = false">
                    <div class="glass flex h-full flex-col rounded-3xl">
                        <div class="flex h-16 items-center justify-between px-5">
                            {{-- Signed in, the wordmark is the way back to the HOP ID dashboard, not to the public home page. --}}
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" title="{{ __('HOP ID dashboard') }}">
                                <x-wordmark class="h-10 w-auto" />
                            </a>
                            <button type="button" class="icon-btn lg:hidden" @click="nav = false" aria-label="{{ __('Close menu') }}">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </div>
                        <nav class="flex-1 overflow-y-auto px-3 pb-3">
                            @include('components.partials.nav')
                        </nav>
                        <div class="flex items-center gap-1 border-t border-slate-500/10 px-3.5 py-1 text-xs text-muted">
                            <a href="{{ route('changelog') }}" class="inline-flex min-h-11 items-center px-1.5 hover:underline">{{ __('Changelog') }}</a>
                            <span aria-hidden="true">·</span>
                            <a href="https://github.com/navi-crwn/HEL.ink" rel="noopener" class="inline-flex min-h-11 items-center px-1.5 hover:underline">GitHub</a>
                        </div>
                    </div>
                </aside>

                <header class="sticky top-0 z-30 px-3 pt-3 sm:px-5">
                    <div class="glass flex h-14 items-center gap-2 rounded-2xl px-2 sm:px-3">
                        <button type="button" class="icon-btn lg:hidden" @click="nav = true" aria-label="{{ __('Open menu') }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h10"/></svg>
                        </button>
                        <p class="truncate pl-1 text-sm font-semibold text-ink">{{ $sectionTitle }}</p>
                        <div class="ml-auto flex items-center gap-1.5">
                            {{-- Accounts with a role: one shortcut to Imperium. The hub stays the same dashboard for everyone. --}}
                            @if ($user->hasAnyRole())
                                <a href="{{ config('hop.imperium_url') }}" class="btn-primary btn-sm whitespace-nowrap" data-imperium-shortcut aria-label="{{ __('Open Imperium') }}" title="{{ __('Open Imperium') }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/></svg>
                                    <span class="hidden sm:inline">{{ __('Open Imperium') }}</span>
                                </a>
                            @endif
                            <x-hop::language-switcher />
                            <button type="button" data-call="toggleTheme" id="theme-toggle-button" class="icon-btn" aria-label="{{ __('Toggle theme') }}" title="{{ __('Toggle theme') }}">
                                <svg class="h-[18px] w-[18px] dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/></svg>
                                <svg class="hidden h-[18px] w-[18px] dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                            </button>
                            <x-hop::account-menu :user="$user" :account-url="route('settings')" :logout-url="route('logout')" />
                        </div>
                    </div>
                </header>

                <main class="app-shell px-3 pb-10 pt-4 sm:px-5 sm:pt-6">
                    <div class="mx-auto w-full @yield('container-width', 'max-w-(--breakpoint-xl) 2xl:max-w-(--breakpoint-2xl)') space-y-5">
                        @unless ($user->hasVerifiedEmail())
                            <div class="glass flex flex-col gap-3 rounded-2xl border-amber-400/40 px-4 py-3 sm:flex-row sm:items-center">
                                <div class="flex flex-1 items-start gap-3">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-300">!</span>
                                    <p class="text-sm text-ink">{!! __('Verify your email to open the apps. We sent a link to :email.', ['email' => '<strong>'.e($user->email).'</strong>']) !!}</p>
                                </div>
                                <form method="POST" action="{{ route('verification.send') }}">
                                    @csrf
                                    <button class="btn-soft btn-sm w-full sm:w-auto">{{ __('Resend email') }}</button>
                                </form>
                            </div>
                        @endunless
                        @isset($header)
                            <div>{{ $header }}</div>
                        @endisset
                        @hasSection('content')
                            @yield('content')
                        @else
                            {{ $slot ?? '' }}
                        @endif
                    </div>
                </main>
            </div>
        @else
            <div class="min-h-screen">
                <main>
                    @hasSection('content')
                        @yield('content')
                    @else
                        {{ $slot ?? '' }}
                    @endif
                </main>
            </div>
        @endauth
        <script nonce="{{ Vite::cspNonce() }}">
            // "Interface style" (HOP / Clean): one cookie on .hel.ink, read by the early script above in every app.
            window.setHopUi = function (v) {
                var d = /(^|\.)hel\.ink$/.test(location.hostname) ? '; domain=.hel.ink; Secure' : '';
                v = v === 'clean' ? 'clean' : 'hop';
                document.cookie = 'hop_ui=' + v + '; path=/; max-age=31536000; SameSite=Lax' + d;
                if (v === 'clean') document.documentElement.dataset.ui = 'clean'; else document.documentElement.removeAttribute('data-ui');
                document.querySelectorAll('[data-ui-choice]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.uiChoice === v ? 'true' : 'false'); });
            };
            document.querySelectorAll('[data-ui-choice]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.uiChoice === (document.documentElement.dataset.ui === 'clean' ? 'clean' : 'hop') ? 'true' : 'false'); });
            window.toggleTheme = function () {
                const isDark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('helink-theme', isDark ? 'dark' : 'light'); } catch (e) {}
                window.dispatchEvent(new CustomEvent('theme-changed'));
            };
        </script>
        @stack('scripts')
    </body>
</html>
