@php
    $marketingLinks = [
        ['label' => __('Apps'), 'href' => route('home').'#apps'],
        ['label' => __('How it works'), 'href' => route('how-it-works')],
        ['label' => __('Security'), 'href' => route('security')],
        ['label' => __('Status'), 'href' => route('status')],
        ['label' => __('Help'), 'href' => route('help')],
    ];
    // The drawer ends with the footer links, so a phone reaches them without scrolling to the end of the page.
    $drawerLinks = [
        ['label' => __('About'), 'href' => route('about')],
        ['label' => __('FAQ'), 'href' => route('faq')],
        ['label' => __('Contact'), 'href' => route('contact')],
        ['label' => __('Changelog'), 'href' => route('changelog')],
        ['label' => __('Terms'), 'href' => route('terms')],
        ['label' => __('Privacy'), 'href' => route('privacy')],
        ['label' => 'GitHub', 'href' => 'https://github.com/navi-crwn/HEL.ink'],
    ];
    $family = \Hop\Ui\Assets::family('hub');
    $loginUrl = hub_url('login');
    $registerUrl = hub_url('register');
    $dashboardUrl = hub_url('dashboard');
@endphp
{{-- Top bar: brand, the page links (wide screens), then primary action · language · theme · menu.
     Below xl the page links, sign-in, the HOP family and the footer links live in a drawer; the app grid
     stays out of the top bar there (the dashboard chrome rule). --}}
<header class="marketing-nav sticky top-0 z-40 px-3 pt-3 sm:px-5" x-data="{ open: false }" @keydown.escape.window="open = false" x-effect="document.documentElement.classList.toggle('drawer-open', open); if (open) $nextTick(() => $refs.close && $refs.close.focus())">
    <nav class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-2 rounded-2xl border border-slate-200/80 bg-white/70 px-2 shadow-xs shadow-slate-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/50 dark:shadow-none sm:px-4">
        <a href="{{ route('home') }}" aria-label="HOP ID" class="marketing-brand flex min-w-0 items-center gap-2.5 text-slate-900 dark:text-white">
            <x-wordmark class="h-10 w-auto" :sub="request()->routeIs('home')" />
        </a>
        <div class="hidden items-center gap-1 text-sm text-slate-600 xl:flex dark:text-white/70">
            @foreach ($marketingLinks as $item)
                <a href="{{ $item['href'] }}" class="rounded-lg px-3 py-1.5 transition hover:bg-slate-900/5 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white">{{ $item['label'] }}</a>
            @endforeach
        </div>
        <div class="flex shrink-0 items-center gap-1 text-sm sm:gap-2">
            <div class="hidden xl:block"><x-hop::app-switcher app="hub" /></div>
            <x-hop::language-switcher />
            @if (! empty($themed))
                <button type="button" class="marketing-icon-btn" aria-label="{{ __('Toggle theme') }}" title="{{ __('Toggle theme') }}" data-toggle-dark>
                    <svg class="h-[18px] w-[18px] dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/></svg>
                    <svg class="hidden h-[18px] w-[18px] dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
            @endif
            @auth
                <a href="{{ $dashboardUrl }}" class="marketing-primary">{{ __('Launcher') }}</a>
            @else
                <a href="{{ $loginUrl }}" class="hidden rounded-xl px-3 py-2 text-slate-600 transition hover:text-slate-900 sm:inline-block dark:text-white/80 dark:hover:text-white">{{ __('Log in') }}</a>
                <a href="{{ $registerUrl }}" class="marketing-primary">{{ __('Sign up') }}</a>
            @endauth
            <button type="button" class="marketing-icon-btn xl:hidden" @click="open = true" :aria-expanded="open" aria-controls="app-menu" aria-label="{{ __('Menu') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </nav>
    <div id="app-menu" x-show="open" x-cloak class="fixed inset-0 z-50 xl:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" x-show="open" x-transition.opacity.duration.200ms @click="open = false" aria-hidden="true"></div>
        <aside class="marketing-drawer" x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
            <div class="flex items-center justify-between">
                <x-wordmark class="h-10 w-auto" />
                <button type="button" class="marketing-icon-btn" @click="open = false" x-ref="close" aria-label="{{ __('Close menu') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <nav class="mt-4" aria-label="{{ __('Menu') }}">
                <ul>
                    @foreach ($marketingLinks as $item)
                        <li><a href="{{ $item['href'] }}" @click="open = false" class="marketing-drawer-link">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="mt-4 grid gap-2">
                @auth
                    <a href="{{ $dashboardUrl }}" class="marketing-drawer-btn primary">{{ __('Launcher') }}</a>
                @else
                    <a href="{{ $registerUrl }}" class="marketing-drawer-btn primary">{{ __('Create HOP ID') }}</a>
                    <a href="{{ $loginUrl }}" class="marketing-drawer-btn">{{ __('Log in') }}</a>
                @endauth
            </div>
            <p class="eyebrow mt-6 px-3">{{ __('HOP family') }}</p>
            <ul class="mt-1">
                @foreach ($family as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="marketing-drawer-link" @if ($item['current']) aria-current="page" @endif>
                            <img src="{{ $item['icons']['mark']['url'] }}" alt="" width="24" height="24" loading="lazy" class="h-6 w-6 shrink-0 rounded-md object-contain">
                            <span>{{ $item['slug'] === 'hub' ? 'HOP ID' : $item['name'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <ul class="marketing-drawer-foot">
                @foreach ($drawerLinks as $item)
                    <li><a href="{{ $item['href'] }}" class="marketing-drawer-link small">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
</header>
