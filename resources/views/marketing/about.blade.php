@php
    $stories = \App\Support\FamilyStory::all();
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="__('About HOP') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
        <div class="space-y-4">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50/80 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                {{ __('The HOP Family') }}
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('About HOP') }}</h1>
            <p class="text-lg leading-relaxed {{ $muted }}">
                {!! __('<strong>HOP</strong> stands for Hop Easy Link: a small, independent family of privacy-first web services that share one central account, one database, and one home at <strong>hel.ink</strong>.', ['hub' => 'hel.ink']) !!}
            </p>
        </div>

        {{-- Origin Story --}}
        <div class="mt-10 space-y-4 text-base leading-relaxed text-slate-700 dark:text-white/80">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('How the family grew') }}</h2>
            <p>
                {{ __('HOP started in 2024 as a single URL shortener. As time went on, link-in-bio pages, an image hosting service, and end-to-end encrypted notes grew alongside it. Initially bundled together, each tool eventually grew to deserve its own dedicated address, its own focus, and its own clean interface.') }}
            </p>
            <p>
                {{ __('Rather than cluttering one bloated app, HOP separated into distinct members with clean URL namespaces. hel.ink acts as the central hub: the anchor where you create and manage your HOP ID, review service status, check security advisories, and launch each app.') }}
            </p>
        </div>

        {{-- HOP ID Backbone --}}
        <div class="mt-10 {{ $card }}">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-sm">
                    ID
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('HOP ID — One Account Everywhere') }}</h3>
                    <p class="text-xs font-mono text-indigo-600 dark:text-indigo-400">hub.hel.ink</p>
                </div>
            </div>
            <p class="mt-4 text-sm leading-relaxed {{ $muted }}">
                {{ __('One free sign-in connects you to LinkHop, BioHop, PixelHop, and NoteHop. You can sign in with your email and password, or use Google Sign-In. You can enable two-factor authentication (TOTP) in settings, export all your account data at any time, or delete your account with immediate cascading removal across every app. When you log out, chained logout terminates active sessions across the entire family.') }}
            </p>
        </div>

        {{-- The Family Apps from Manifest --}}
        <div class="mt-12 space-y-6">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('The HOP Family Apps') }}</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach ($stories as $slug => $app)
                    <div class="{{ $card }} flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <img src="{{ $app['mark'] }}" alt="" width="36" height="36" class="h-9 w-9 rounded-xl">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider" style="color: {{ $app['color'] }}">{{ $app['kicker'] }}</p>
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $app['name'] }}</h3>
                                </div>
                            </div>
                            <p class="mt-3 text-sm {{ $muted }}">{{ $app['story'] }}</p>
                            <ul class="mt-4 space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                                @foreach ($app['features'] as $feature)
                                    <li class="flex items-start gap-1.5">
                                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $app['color'] }}"></span>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="mt-6 flex items-center justify-between border-t border-slate-200/60 pt-4 dark:border-white/10">
                            <a href="{{ $app['url'] }}" class="text-xs font-semibold underline underline-offset-2 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $app['host'] }} &rarr;
                            </a>
                            <a href="{{ app_page_url($slug) }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:text-white/80 dark:hover:bg-white/5">
                                {{ __('Overview') }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Principles & Stewardship --}}
        <div class="mt-12 space-y-4 text-base leading-relaxed text-slate-700 dark:text-white/80">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Our Principles') }}</h2>
            <ul class="list-disc space-y-2 ps-5 text-sm">
                <li><strong>{{ __('Free & Open Source:') }}</strong> {{ __('Every application is licensed under the MIT License. The code is transparent and available on GitHub for anyone to audit or self-host.') }}</li>
                <li><strong>{{ __('Zero Advertising & Surveillance:') }}</strong> {{ __('We do not sell data, track visitors across third-party websites, or display advertising.') }}</li>
                <li><strong>{{ __('One Small Server:') }}</strong> {{ __('HOP runs efficiently on a single modest server behind Cloudflare with nginx, PHP-FPM, and Go microservices. Efficiency is treated as a core design virtue.') }}</li>
                <li><strong>{{ __('Independent Stewardship:') }}</strong> {{ __('Maintained by navi-crwn with a dedication to craft, minimalism, and digital privacy.') }}</li>
            </ul>
        </div>

        {{-- Contact & Footer Note --}}
        <div class="mt-10 border-t border-slate-200/80 pt-6 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
            <p>
                {!! __('Questions, suggestions, or bug reports? Feel free to <a class="underline" href=":contact">contact us</a> or submit an issue on <a class="underline" href=":github">GitHub</a>.', ['contact' => route('contact'), 'github' => 'https://github.com/navi-crwn/HEL.ink/issues']) !!}
            </p>
        </div>
    </section>
</x-marketing-layout>
