@php
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="$app['name'].' · HOP'" :description="$app['story']" :themed="true">
    {{-- Hero Section --}}
    <section class="relative mx-auto max-w-5xl px-4 pt-14 pb-8 sm:px-6">
        <div class="flex flex-col items-center text-center space-y-4">
            <img src="{{ $app['mark'] }}" alt="" width="64" height="64" class="h-16 w-16 rounded-2xl shadow-lg ring-1 ring-black/5 dark:ring-white/10">
            <div>
                <span class="text-xs font-bold uppercase tracking-[0.2em]" style="color: {{ $app['color'] }}">{{ $app['kicker'] }}</span>
                <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl dark:text-white">{{ $app['name'] }}</h1>
            </div>
            <p class="max-w-2xl text-lg leading-relaxed {{ $muted }}">{{ $app['story'] }}</p>

            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                <a href="{{ $app['url'] }}" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:opacity-90" style="background-color: {{ $app['color'] }}">
                    {{ $app['cta'] }} &rarr;
                </a>
                <a href="{{ $app['url'] }}/features" class="rounded-xl border border-slate-200 bg-white/80 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">
                    {{ __('All Features') }}
                </a>
                @if ($app['repo'])
                    <a href="{{ $app['repo'] }}" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-slate-200 bg-white/80 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/70">
                        GitHub (MIT)
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-3 text-xs font-mono text-slate-500 pt-1" dir="ltr">
                <span class="underline">{{ $app['host'] }}</span>
                <span>&bull;</span>
                @if (($status['ok'] ?? null) === true)
                    <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-bold">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('Status: UP') }}
                    </span>
                @elseif (($status['ok'] ?? null) === false)
                    <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-bold">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        {{ __('Status: DOWN') }}
                    </span>
                @else
                    <span>{{ __('Status: Online') }}</span>
                @endif
            </div>
        </div>

        {{-- Visual Showcase: Browser Frame with Demo Screenshot --}}
        <div class="mt-12 overflow-hidden rounded-3xl border border-slate-200/80 bg-slate-900/5 p-2 shadow-2xl dark:border-white/10 dark:bg-white/5">
            <div class="overflow-hidden rounded-2xl border border-slate-200/60 bg-white dark:border-white/10 dark:bg-slate-950">
                <div class="flex items-center gap-2 border-b border-slate-200/80 bg-slate-100/80 px-4 py-2.5 dark:border-white/10 dark:bg-white/5">
                    <div class="flex gap-1.5">
                        <div class="h-2.5 w-2.5 rounded-full bg-rose-400/80"></div>
                        <div class="h-2.5 w-2.5 rounded-full bg-amber-400/80"></div>
                        <div class="h-2.5 w-2.5 rounded-full bg-emerald-400/80"></div>
                    </div>
                    <div class="mx-auto rounded-md bg-white/70 px-6 py-0.5 font-mono text-[11px] text-slate-500 shadow-sm dark:bg-white/5 dark:text-slate-400" dir="ltr">
                        https://{{ $app['host'] }}
                    </div>
                </div>
                <img src="{{ $app['screenshot'] }}" alt="{{ $app['name'] }} Preview" class="w-full object-cover shadow-inner" loading="lazy">
            </div>
        </div>
    </section>

    {{-- Bento Grid Features --}}
    <section class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider" style="color: {{ $app['color'] }}">{{ __('Core Capabilities') }}</span>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('Bento Feature Highlights') }}</h2>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @foreach ($app['features'] as $idx => $feature)
                <div class="{{ $card }} flex items-start gap-4 transition hover:border-slate-300 dark:hover:border-white/20">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-white font-bold text-sm shadow-sm" style="background-color: {{ $app['color'] }}">
                        0{{ $idx + 1 }}
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $feature }}</h3>
                        <p class="mt-1 text-xs leading-relaxed {{ $muted }}">
                            {{ __('Built natively for performance, security, and full integration with your central HOP ID.') }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it Works 3 Steps --}}
    @if (!empty($app['steps']))
        <section class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider" style="color: {{ $app['color'] }}">{{ __('Workflow') }}</span>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('How It Works in 3 Steps') }}</h2>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-3">
                @foreach ($app['steps'] as $idx => $step)
                    <div class="{{ $card }} space-y-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-900/5 font-mono text-xs font-bold text-slate-700 dark:bg-white/10 dark:text-slate-200">
                            {{ $idx + 1 }}
                        </span>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $step[0] }}</h3>
                        <p class="text-xs leading-relaxed {{ $muted }}">{{ $step[1] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- App Specific FAQ --}}
    @if (!empty($app['faqs']))
        <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider" style="color: {{ $app['color'] }}">{{ __('Questions & Answers') }}</span>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('Frequently Asked Questions') }}</h2>
            </div>
            <dl class="mt-6 space-y-3">
                @foreach ($app['faqs'] as $faq)
                    <details class="{{ $card }}">
                        <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ $faq[0] }}</summary>
                        <p class="mt-3 text-xs leading-relaxed {{ $muted }}">{{ $faq[1] }}</p>
                    </details>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- Final CTA --}}
    <section class="mx-auto max-w-5xl px-4 pb-16 pt-6 sm:px-6">
        <div class="rounded-3xl p-8 sm:p-12 text-white shadow-xl" style="background: linear-gradient(135deg, {{ $app['color'] }}dd, #0f172a)">
            <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('Get started with :app today', ['app' => $app['name']]) }}</h2>
            <p class="mt-3 max-w-xl text-sm text-white/80 leading-relaxed">
                {{ __('Free forever. One HOP ID connects you to every app in the HOP family with zero setup fees or third-party tracking.') }}
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ $app['url'] }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 shadow transition hover:bg-slate-100">
                    {{ $app['cta'] }}
                </a>
                <a href="{{ route('home') }}" class="rounded-xl border border-white/40 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                    {{ __('Back to HOP Family') }}
                </a>
            </div>
        </div>
    </section>
</x-marketing-layout>
