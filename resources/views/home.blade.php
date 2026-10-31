@php
    $stories = \App\Support\FamilyStory::all();
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="'HEL.ink · '.__('One hop to every app.')" :themed="true" :back="false" :alternates="true">
    {{-- Top Live Status Ticker (Item 4.0.8) --}}
    <x-status-ticker :status="$status" :stories="$stories" />

    {{-- Icon sprite --}}
    <svg xmlns="http://www.w3.org/2000/svg" class="hidden" aria-hidden="true">
        <symbol id="i-key" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/></symbol>
        <symbol id="i-hop" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18c3-8 13-8 16 0"/><path d="m16 14 4 4-4 1"/></symbol>
        <symbol id="i-out" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
        <symbol id="i-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></symbol>
        <symbol id="i-code" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></symbol>
        <symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></symbol>
    </svg>

    {{-- Hero Section (Amnesia style bold typography & 3D tiles) --}}
    <section class="relative mx-auto max-w-6xl px-4 pb-12 pt-16 sm:px-6 sm:pt-24">
        <div class="pointer-events-none absolute inset-y-0 end-0 w-5/12 max-lg:hidden" aria-hidden="true">
            <x-hop::float mode="section" />
        </div>
        <div class="relative z-10 max-w-2xl">
            <div class="flex flex-wrap items-center gap-2">
                <a href="https://github.com/navi-crwn/HEL.ink" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-xs text-slate-700 shadow-sm transition hover:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white/80">
                    <span class="rounded-full bg-gradient-to-r from-indigo-500 to-fuchsia-500 px-2 py-0.5 font-mono text-[10px] font-bold text-white">MIT</span>
                    <span>{{ __('Open source on GitHub') }} &rarr;</span>
                </a>
            </div>
            <h1 class="mt-5 font-syne text-[clamp(2.5rem,8vw,4.5rem)] font-extrabold leading-[1.05] tracking-tight text-slate-900 dark:text-white">
                {{ __('One hop to every app.') }}
            </h1>
            <p class="mt-5 text-lg leading-relaxed {{ $muted }}">
                {{ __('hel.ink is home to the HOP family: short links, a link-in-bio page, image hosting and encrypted notes. One free account, one sign-in, every app.') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ hub_url('register') }}" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-white/90">
                    {{ __('Create your HOP ID') }}
                </a>
                <a href="#apps" class="rounded-xl border border-slate-200 bg-white/80 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">
                    {{ __('See the apps') }}
                </a>
                <a href="{{ route('how-it-works') }}" class="inline-flex items-center gap-1.5 px-3 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-white/70 dark:hover:text-white">
                    {{ __('How it works') }} <svg class="h-4 w-4 rtl:-scale-x-100"><use href="#i-arrow"/></svg>
                </a>
            </div>
            <p class="mt-6 font-mono text-xs text-slate-500 dark:text-white/50">
                {{ __('Free · No ads · No trackers · MIT licensed') }}
            </p>
        </div>
    </section>

    {{-- Announcements Banner --}}
    @foreach ($announcements as $a)
        <section class="mx-auto max-w-6xl px-4 sm:px-6 mb-6">
            <a href="{{ route('announcements.show', $a) }}" class="block rounded-2xl border border-amber-300/60 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 shadow-sm transition hover:bg-amber-100/80 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100">
                <strong>{{ $a->title }}</strong> · {{ $a->published_at->format('j M Y') }} &rarr;
            </a>
        </section>
    @endforeach

    {{-- App Tabs (#linkhop, #biohop, …) with Real Screenshots (Item 4.0.6) --}}
    <section id="apps" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-14 sm:px-6">
        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ __('The Ecosystem') }}</span>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('Four apps, one account') }}</h2>
        </div>
        
        <div class="mt-6 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="{{ __('HOP apps') }}" data-family-tabs>
            @foreach ($stories as $slug => $app)
                <button type="button" role="tab" id="tab-{{ $slug }}" data-app="{{ $slug }}" aria-controls="panel-{{ $slug }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                        class="family-tab flex flex-none items-center gap-2 rounded-2xl border border-slate-200/80 bg-white/70 px-4 py-2.5 text-sm font-semibold text-slate-700 transition dark:border-white/10 dark:bg-white/5 dark:text-white/80">
                    <img src="{{ $app['mark'] }}" alt="" width="24" height="24" class="h-6 w-6 rounded-lg">{{ $app['name'] }}
                </button>
            @endforeach
        </div>

        @foreach ($stories as $slug => $app)
            @php($s = $status[$slug] ?? null)
            <div role="tabpanel" id="panel-{{ $slug }}" aria-labelledby="tab-{{ $slug }}" tabindex="0" @if (! $loop->first) hidden @endif
                 class="mt-6 grid gap-8 {{ $card }} lg:grid-cols-5 items-center">
                {{-- Left: Details & Features --}}
                <div class="lg:col-span-3 space-y-4">
                    <div class="flex items-center gap-3">
                        <img src="{{ $app['mark'] }}" alt="" width="48" height="48" class="h-12 w-12 rounded-2xl shadow-md">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em]" style="color: {{ $app['color'] }}">{{ $app['kicker'] }}</p>
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $app['name'] }}</h3>
                        </div>
                        @if (($s['ok'] ?? null) !== null)
                            <span @class(['ms-auto rounded-full px-2.5 py-0.5 text-[11px] font-semibold', 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300' => $s['ok'], 'bg-rose-500/15 text-rose-600 dark:text-rose-300' => ! $s['ok']])>{{ $s['ok'] ? __('up') : __('down') }}</span>
                        @endif
                    </div>
                    <p class="text-base leading-relaxed {{ $muted }}">{{ $app['story'] }}</p>
                    
                    {{-- Features List --}}
                    <ul class="grid gap-2.5 sm:grid-cols-2 pt-2">
                        @foreach ($app['features'] as $feature)
                            <li class="flex items-start gap-2 text-xs font-medium text-slate-700 dark:text-white/80">
                                <svg class="mt-0.5 h-4 w-4 shrink-0" style="color: {{ $app['color'] }}"><use href="#i-check"/></svg>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="flex flex-wrap items-center gap-3 pt-4">
                        <a href="{{ $app['url'] }}" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white shadow transition hover:opacity-90" style="background-color: {{ $app['color'] }}">
                            {{ $app['cta'] }} <svg class="h-4 w-4 rtl:-scale-x-100"><use href="#i-arrow"/></svg>
                        </a>
                        <a href="{{ app_page_url($slug) }}" class="rounded-xl border border-slate-200 bg-white/80 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">
                            {{ __('More about :app', ['app' => $app['name']]) }}
                        </a>
                    </div>
                    <p class="font-mono text-xs text-slate-500 dark:text-white/50" dir="ltr">{{ $app['host'] }}</p>
                </div>

                {{-- Right: Browser Chrome Frame with Demo Screenshot --}}
                <div class="lg:col-span-2 overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-900/5 p-1.5 shadow-xl dark:border-white/10 dark:bg-white/5">
                    <div class="overflow-hidden rounded-xl border border-slate-200/60 bg-white dark:border-white/10 dark:bg-slate-950">
                        <div class="flex items-center gap-1.5 border-b border-slate-200/80 bg-slate-100/80 px-3 py-1.5 dark:border-white/10 dark:bg-white/5">
                            <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            <span class="ms-2 font-mono text-[10px] text-slate-400 truncate" dir="ltr">https://{{ $app['host'] }}</span>
                        </div>
                        <img src="{{ $app['screenshot'] }}" alt="{{ $app['name'] }} Preview" class="w-full object-cover" loading="lazy">
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    {{-- SSO Flow Illustration (Item 4.0.6) --}}
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div class="{{ $card }} space-y-6">
            <div class="space-y-1 text-center max-w-xl mx-auto">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ __('Single Sign-On Architecture') }}</span>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('How HOP ID Connects Everything') }}</h2>
                <p class="text-xs {{ $muted }}">{{ __('Zero duplicate sign-ups. One master session verifies you across each independent member service.') }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-4 pt-4">
                <div class="rounded-2xl border border-slate-200/60 bg-white/50 p-4 text-center dark:border-white/10 dark:bg-white/5 space-y-2">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-xs">01</div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('1. Sign In at Hub') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Sign in with your email, password, or Google at hub.hel.ink.') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200/60 bg-white/50 p-4 text-center dark:border-white/10 dark:bg-white/5 space-y-2">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-xs">02</div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('2. Signed Ticket') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('A short-lived HMAC-signed ticket verifies your identity safely.') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200/60 bg-white/50 p-4 text-center dark:border-white/10 dark:bg-white/5 space-y-2">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-xs">03</div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('3. Member Apps') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('LinkHop, BioHop, PixelHop, and NoteHop accept the session.') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200/60 bg-white/50 p-4 text-center dark:border-white/10 dark:bg-white/5 space-y-2">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-xs">04</div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('4. Chained Logout') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Sign out from any app to terminate active sessions everywhere.') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Numbered Workflow Steps --}}
    <section id="how" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-12 sm:px-6">
        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ __('Workflow') }}</span>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('How HOP works') }}</h2>
        </div>
        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ([['i-key', __('One HOP ID'), __('Sign up once at the hub, with a password or Google, and turn on two-factor sign-in.')], ['i-hop', __('Hop between apps'), __('Each app signs you in with a short-lived ticket from the hub. Nothing to type twice.')], ['i-out', __('Sign out everywhere'), __('Signing out at the hub signs you out of every app in one go.')]] as $step)
                <div class="{{ $card }}">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"><svg class="h-5 w-5"><use href="#{{ $step[0] }}"/></svg></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $step[1] }}</h3>
                    <p class="mt-1 text-sm {{ $muted }}">{{ $step[2] }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-6 text-sm"><a class="font-semibold text-indigo-600 underline underline-offset-4 dark:text-indigo-300" href="{{ route('how-it-works') }}">{{ __('Read the details') }}</a></p>
    </section>

    {{-- Privacy and Security --}}
    <section id="privacy" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-12 sm:px-6">
        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Principles') }}</span>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('Private by design') }}</h2>
        </div>
        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ([['i-eye-off', __('No ads, no trackers'), __('No ad networks and no third-party analytics on HOP pages. Visitor counts use a keyed hash of the IP address, never the address.')], ['i-lock', __('Notes nobody else can read'), __('NoteHop encrypts in your browser. The key stays in the link and never reaches the server.')], ['i-shield', __('Safe links'), __('Short links and bio pages are checked for phishing and malware, and anyone can report abuse.')]] as $item)
                <div class="{{ $card }}">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300"><svg class="h-5 w-5"><use href="#{{ $item[0] }}"/></svg></span>
                    <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $item[1] }}</h3>
                    <p class="mt-1 text-sm {{ $muted }}">{{ $item[2] }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-6 text-sm"><a class="font-semibold text-indigo-600 underline underline-offset-4 dark:text-indigo-300" href="{{ route('security') }}">{{ __('Security') }}</a> · <a class="font-semibold text-indigo-600 underline underline-offset-4 dark:text-indigo-300" href="{{ route('privacy') }}">{{ __('Privacy policy') }}</a></p>
    </section>

    {{-- Open Source & GitHub Repos --}}
    <section id="open" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-12 sm:px-6">
        <div class="grid gap-8 {{ $card }} lg:grid-cols-2">
            <div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-300"><svg class="h-5 w-5"><use href="#i-code"/></svg></span>
                <h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('Built in the open') }}</h2>
                <p class="mt-3 {{ $muted }}">{{ __('Every HOP app is MIT licensed. Read the code, report a bug, send a fix, or run the whole family on your own server.') }}</p>
                <p class="mt-5 text-sm"><a class="font-semibold text-indigo-600 underline underline-offset-4 dark:text-indigo-300" href="{{ route('open-source') }}">{{ __('Run your own') }}</a></p>
            </div>
            <ul class="grid gap-2 sm:grid-cols-2" dir="ltr">
                <li><a class="block rounded-2xl border border-slate-200/70 bg-white/50 px-4 py-3 font-mono text-sm text-slate-700 hover:border-indigo-400 dark:border-white/10 dark:bg-white/5 dark:text-white/80" href="https://github.com/navi-crwn/HEL.ink">navi-crwn/HEL.ink</a></li>
                @foreach ($stories as $app)
                    @if ($app['repo'])
                        <li><a class="block rounded-2xl border border-slate-200/70 bg-white/50 px-4 py-3 font-mono text-sm text-slate-700 hover:border-indigo-400 dark:border-white/10 dark:bg-white/5 dark:text-white/80" href="{{ $app['repo'] }}">{{ \Illuminate\Support\Str::after($app['repo'], 'github.com/') }}</a></li>
                    @endif
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Condensed Status Section (Item 4.0.8: ticker is at top, this summarizes) --}}
    <section id="status" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-8 sm:px-6">
        <div class="{{ $card }} flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="space-y-1 text-center sm:text-left">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('System Status') }}</h2>
                <p class="text-xs {{ $muted }}">{{ __('Real-time latency and uptime monitored each minute by our automated watchdog.') }}</p>
            </div>
            <a href="{{ route('status') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-white/90">
                {{ __('Full status and history') }} &rarr;
            </a>
        </div>
    </section>

    {{-- Questions / FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl scroll-mt-24 px-4 py-12 sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('Questions') }}</h2>
        <div class="mt-6 space-y-3">
            @foreach ([[__('Is HOP really free?'), __('Yes. There is no paid plan and no locked feature in any app.')], [__('Do I need one account per app?'), __('No. One HOP ID signs you in to LinkHop, BioHop, PixelHop and NoteHop.')], [__('Who runs HOP?'), __('One person, on one small server. The code is public so anyone can check it.')], [__('Can I delete everything?'), __('Yes. Download your data or delete your account in your HOP ID settings; every app deletes your content too.')]] as $qa)
                <details class="rounded-2xl border border-slate-200/80 bg-white/70 p-5 dark:border-white/10 dark:bg-white/3">
                    <summary class="cursor-pointer list-none font-semibold text-slate-900 dark:text-white">{{ $qa[0] }}</summary>
                    <p class="mt-3 text-sm {{ $muted }}">{{ $qa[1] }}</p>
                </details>
            @endforeach
        </div>
        <p class="mt-6 text-sm"><a class="font-semibold text-indigo-600 underline underline-offset-4 dark:text-indigo-300" href="{{ route('faq') }}">{{ __('More answers') }}</a></p>
    </section>

    {{-- Call to Action --}}
    <section id="contact" class="mx-auto max-w-6xl scroll-mt-24 px-4 pb-16 pt-6 sm:px-6">
        <div class="rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-8 text-white sm:p-12 shadow-2xl">
            <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('One account. Every app. Free.') }}</h2>
            <p class="mt-3 text-sm text-white/80 max-w-xl">{{ __('Sign up for your free HOP ID in seconds. No credit card, no personal phone number, no surveillance trackers.') }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ hub_url('register') }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 shadow transition hover:bg-slate-100">{{ __('Create your HOP ID') }}</a>
                <a href="{{ route('signup') }}" class="rounded-xl border border-white/40 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">{{ __('How to sign up') }}</a>
            </div>
            <p class="mt-8 text-xs text-white/70">{{ __('Questions or a bug?') }} <a class="underline" href="https://github.com/navi-crwn/HEL.ink/issues/new/choose">{{ __('Open an issue') }}</a> · <a class="underline" href="https://github.com/navi-crwn/HEL.ink/security/advisories/new">{{ __('Report a security problem') }}</a> · <a class="underline" href="{{ route('contact') }}">{{ __('Contact') }}</a></p>
        </div>
    </section>
</x-marketing-layout>
