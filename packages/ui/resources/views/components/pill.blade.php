{{-- The HOP app switcher: one pill per app, the current one filled. --}}
@props(['size' => 'sm'])
@php $apps = \Hop\Ui\Apps::all(); @endphp
<nav {{ $attributes->merge(['class' => 'hop-pill inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-full border border-slate-200/80 bg-white/70 p-1 backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/50']) }} aria-label="HOP apps">
    @foreach ($apps as $app)
        <a href="{{ $app['current'] ? '#' : $app['open'] }}"
           @if ($app['current']) aria-current="page" @endif
           class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition {{ $app['current'] ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:bg-slate-900/5 dark:text-white/70 dark:hover:bg-white/10' }}">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ \Hop\Ui\Apps::icon($app['icon']) }}"/></svg>
            {{ $app['name'] }}
        </a>
    @endforeach
</nav>
