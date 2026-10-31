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
        @if ($history->isNotEmpty())
            <h2 class="mt-10 text-lg font-semibold text-slate-900 dark:text-white">{{ __('Failures in the last 24 hours') }}</h2>
            <ul class="mt-3 space-y-1 text-sm text-slate-600 dark:text-white/70">
                @foreach ($history as $h)
                    <li>{{ $h->checked_at->format('H:i') }} · {{ $h->app_slug }} ({{ $h->kind }}) · {{ $h->error }}</li>
                @endforeach
            </ul>
        @endif
        <p class="mt-8 text-xs text-slate-500 dark:text-white/60">{!! __('Machine-readable: <a class="underline" href=":url">status.json</a>', ['url' => route('status.json')]) !!}</p>
    </section>
</x-marketing-layout>
