{{-- One HOP app, with its watchdog status and an "Open" button (signed in through SSO). --}}
@props(['app', 'state' => null, 'open' => true])
@php
    $ok = $state['ok'] ?? null;
    $tone = ['indigo' => 'from-indigo-500 to-sky-500', 'fuchsia' => 'from-fuchsia-500 to-rose-500', 'sky' => 'from-sky-500 to-cyan-400'][$app->color] ?? 'from-slate-600 to-slate-400';
@endphp
<div {{ $attributes->merge(['class' => 'glass flex flex-col rounded-2xl p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-linear-to-br {{ $tone }} text-white shadow-lg shadow-slate-900/10">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ \Hop\Ui\Apps::icon($app->icon) }}"/></svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-ink">{{ $app->name }}</h3>
                <p class="text-xs text-muted">{{ parse_url($app->url, PHP_URL_HOST) }}</p>
            </div>
        </div>
        @if ($ok === null)
            <span class="chip-slate" title="No check yet">unknown</span>
        @elseif ($ok)
            <span class="chip-green" title="Last check {{ $state['checked_at']?->diffForHumans() }}">up</span>
        @else
            <span class="chip-red" title="{{ $state['error'] }}">down</span>
        @endif
    </div>
    <p class="mt-3 flex-1 text-sm text-muted">{{ $app->tagline }}</p>
    @if ($open)
        <div class="mt-4 flex items-center gap-2">
            @if ($app->isConfigured())
                <a href="{{ route('apps.open', ['app' => $app->slug]) }}" class="btn-primary btn-sm">Open</a>
            @else
                <span class="btn-soft btn-sm cursor-not-allowed opacity-60" title="Not configured (URL or secret missing)">Open</span>
            @endif
            <a href="{{ $app->url }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Visit site</a>
        </div>
    @endif
</div>
