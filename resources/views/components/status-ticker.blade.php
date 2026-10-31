@props(['status' => [], 'stories' => []])
<aside class="hop-ticker-bar border-b border-slate-200/80 bg-white/70 backdrop-blur-md dark:border-white/10 dark:bg-slate-950/50" aria-label="{{ __('Service Status Ticker') }}">
    <div class="mx-auto flex max-w-6xl items-center overflow-hidden px-4 py-1.5 sm:px-6">
        <div class="flex shrink-0 items-center gap-2 pe-4 border-e border-slate-200/80 dark:border-white/10">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <a href="{{ route('status') }}" class="font-mono text-[11px] font-bold uppercase tracking-wider text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400">
                {{ __('Status') }}
            </a>
        </div>
        <div class="hop-ticker-container flex-1 overflow-x-auto no-scrollbar" tabindex="0" role="region" aria-label="{{ __('Current System Status') }}">
            <ul class="hop-ticker-track flex items-center gap-6 ps-4 whitespace-nowrap" aria-live="off">
                @foreach ($stories as $slug => $app)
                    @php($s = $status[$slug] ?? null)
                    <li class="inline-flex items-center gap-1.5 font-mono text-xs">
                        <img src="{{ $app['mark'] }}" alt="" width="16" height="16" class="h-4 w-4 rounded">
                        <span class="font-sans font-semibold text-slate-800 dark:text-slate-200">{{ $app['name'] }}</span>
                        @if (($s['ok'] ?? null) === true)
                            <span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-bold">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('UP') }}
                            </span>
                        @elseif (($s['ok'] ?? null) === false)
                            <span class="inline-flex items-center gap-1 text-[11px] text-rose-600 dark:text-rose-400 font-bold">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                {{ __('DOWN') }}
                            </span>
                        @else
                            <span class="text-[11px] text-slate-400">{{ __('ONLINE') }}</span>
                        @endif
                        @if (isset($s['latency_ms']))
                            <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ $s['latency_ms'] }}ms</span>
                        @endif
                        @if (isset($s['uptime']))
                            <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ $s['uptime'] }}%</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="shrink-0 ps-3">
            <a href="{{ route('status') }}" class="font-mono text-xs text-indigo-600 hover:underline dark:text-indigo-400">
                {{ __('Details') }} &rarr;
            </a>
        </div>
    </div>
</aside>
