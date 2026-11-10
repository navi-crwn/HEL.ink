<x-marketing-layout :title="__('Service status') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('Service status') }}</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-white/70">{{ __('Each app\'s health endpoint is checked every minute. Uptime is over the last 24 hours.') }}</p>
        <div class="mt-8 divide-y divide-slate-200/80 rounded-3xl border border-slate-200/80 bg-white/70 dark:divide-white/10 dark:border-white/10 dark:bg-white/3">
            @forelse ($status as $slug => $s)
                <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span class="h-2.5 w-2.5 rounded-full {{ $s['ok'] === null ? 'bg-slate-400' : ($s['ok'] ? 'bg-emerald-500' : 'bg-rose-500') }}"></span>
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $s['app']->name }}</span>
                    <span class="text-xs text-slate-500 dark:text-white/60">{{ parse_url($s['app']->url, PHP_URL_HOST) }}</span>
                    <span class="ml-auto text-xs text-slate-600 dark:text-white/70">
                        @if ($s['ok'] === null) {{ __('not checked yet') }}
                        @else {{ $s['ok'] ? __('operational') : __('down') }} · {{ $s['latency_ms'] }} ms · {{ $s['uptime'] !== null ? $s['uptime'].'% (24h)' : '' }} · {{ $s['checked_at']?->diffForHumans() }}
                        @endif
                    </span>
                    @if ($s['ok'] === false && $s['error'])
                        <p class="w-full text-xs text-rose-600 dark:text-rose-300">{{ $s['error'] }}</p>
                    @endif
                </div>
            @empty
                <p class="px-5 py-4 text-sm text-slate-600 dark:text-white/70">{{ __('No apps configured.') }}</p>
            @endforelse
        </div>
        <h2 class="mt-10 text-lg font-semibold text-slate-900 dark:text-white">{{ __('Failures in the last 24 hours') }}</h2>
        @if ($failures->isEmpty())
            <p class="status-failures-empty mt-3 flex items-center gap-2.5 rounded-2xl border border-slate-200/80 bg-white/70 px-5 py-4 text-sm text-slate-600 dark:border-white/10 dark:bg-white/3 dark:text-white/70">
                <svg class="h-5 w-5 shrink-0 text-emerald-500" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8.25"/><path d="m6.5 10.25 2.5 2.5 4.5-5"/></svg>
                {{ __('No failures in the last 24 hours') }}
            </p>
        @else
            <ul class="status-failures mt-3 divide-y divide-slate-200/80 rounded-3xl border border-slate-200/80 bg-white/70 dark:divide-white/10 dark:border-white/10 dark:bg-white/3">
                @foreach ($failures as $slug => $f)
                    @php
                        $ok = $status[$slug]['ok'] ?? null;
                        $hourStart = now()->startOfHour();
                    @endphp
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4">
                        <span class="flex min-w-0 items-center gap-2.5 sm:w-44">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $ok === null ? 'bg-slate-400' : ($ok ? 'bg-emerald-500' : 'bg-rose-500') }}" aria-hidden="true"></span>
                            <span class="truncate font-semibold text-slate-900 dark:text-white">{{ $f['app']?->name ?? $slug }}</span>
                        </span>
                        <span class="text-sm text-slate-600 sm:w-24 dark:text-white/70"><span class="text-xs text-slate-500 dark:text-white/60">{{ __('Failures') }}</span> <b class="font-semibold tabular-nums text-rose-600 dark:text-rose-300">{{ $f['count'] }}</b></span>
                        <span class="text-sm text-slate-600 sm:w-56 dark:text-white/70"><span class="text-xs text-slate-500 dark:text-white/60">{{ __('Last failure') }}</span> <time datetime="{{ $f['last']->toIso8601String() }}" title="{{ $f['last']->format('Y-m-d H:i:s') }} UTC" class="tabular-nums">{{ $f['last']->diffForHumans() }}</time></span>
                        <span class="ml-auto flex h-5 items-end gap-0.5" role="img" aria-label="{{ __('Failures per hour, oldest first') }}: {{ implode(', ', $f['hours']) }}">
                            @foreach ($f['hours'] as $i => $n)
                                <span class="h-5 w-1.5 shrink-0 rounded-sm {{ $n ? 'bg-rose-500' : 'bg-slate-200 dark:bg-white/10' }}" title="{{ $hourStart->copy()->subHours(23 - $i)->format('H:00') }} · {{ $n }}"></span>
                            @endforeach
                        </span>
                        @if ($f['error'])
                            <p class="w-full break-words text-xs text-rose-600 dark:text-rose-300">{{ $f['error'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="mt-8 text-xs text-slate-500 dark:text-white/60">{!! __('Machine-readable: <a class="underline" href=":url">status.json</a>', ['url' => route('status.json')]) !!}</p>
    </section>
</x-marketing-layout>
