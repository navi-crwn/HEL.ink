{{-- One HOP app, compact: mark, name, watchdog status, "Open" (signed in through SSO) and "Visit site".
     Optional `stats` slot: per-app numbers from the app itself. Nothing is rendered while it is empty. --}}
@props(['app', 'state' => null, 'open' => true])
@php
    $ok = $state['ok'] ?? null;
    $tone = ['indigo' => 'from-indigo-500 to-sky-500', 'fuchsia' => 'from-fuchsia-500 to-rose-500', 'sky' => 'from-sky-500 to-cyan-400'][$app->color] ?? 'from-slate-600 to-slate-400';
    $familyApp = collect(\Hop\Ui\Assets::family())->firstWhere('slug', $app->slug);
@endphp
<div {{ $attributes->merge(['class' => 'glass flex min-w-0 flex-col gap-3 rounded-2xl p-4 transition-shadow hover:shadow-lg hover:shadow-slate-900/5']) }}>
    <div class="flex items-center gap-3">
        @if($familyApp)
            <img src="{{ $familyApp['icons']['mark']['url'] }}" alt="" width="36" height="36" class="h-9 w-9 shrink-0">
        @else
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-br {{ $tone }} text-white shadow-md shadow-slate-900/10">
                <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ \Hop\Ui\Apps::icon($app->icon) }}"/></svg>
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <h3 class="truncate text-sm font-semibold text-ink">{{ $app->name }}</h3>
            <p class="truncate font-mono text-[11px] text-muted">{{ parse_url($app->url, PHP_URL_HOST) }}</p>
        </div>
        @if ($ok === null)
            <span class="chip-gray shrink-0" title="{{ __('No check yet') }}">{{ __('Unknown') }}</span>
        @elseif ($ok)
            <span class="chip-green shrink-0" title="{{ __('Last check :when', ['when' => $state['checked_at']?->diffForHumans()]) }}">{{ __('Up') }}</span>
        @else
            <span class="chip-red shrink-0" title="{{ $state['error'] }}">{{ __('Down') }}</span>
        @endif
    </div>
    @if (isset($stats) && ! $stats->isEmpty())
        {{-- Per-app numbers (links, pages, images, notes) will be rendered here once the apps send them. --}}
        <div class="glass-inset rounded-xl px-3 py-2 text-xs">{{ $stats }}</div>
    @endif
    @if ($open)
        <div class="mt-auto flex flex-wrap items-center gap-2">
            @if ($app->isConfigured())
                <a href="{{ route('apps.open', ['app' => $app->slug]) }}" class="btn-primary btn-sm whitespace-nowrap">{{ __('Open') }} <span aria-hidden="true">↗</span></a>
            @else
                <span class="btn-soft btn-sm cursor-not-allowed whitespace-nowrap opacity-60" title="{{ __('Not configured (URL or secret missing)') }}">{{ __('Open') }}</span>
            @endif
            <a href="{{ $app->url }}" class="btn-ghost btn-sm whitespace-nowrap" target="_blank" rel="noopener">{{ __('Visit site') }}</a>
        </div>
    @endif
</div>
