<x-marketing-layout :title="$app['name'].' · HOP'" :description="$app['story']" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <div class="flex items-center gap-4">
            <img src="{{ $app['mark'] }}" alt="" width="56" height="56" class="h-14 w-14 rounded-2xl">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em]" style="color: {{ $app['color'] }}">{{ $app['kicker'] }}</p>
                <h1 class="text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $app['name'] }}</h1>
            </div>
        </div>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p class="text-lg">{{ $app['story'] }}</p>
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($app['features'] as $feature)
                    <li class="rounded-2xl border border-slate-200/80 bg-white/70 px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">{{ $feature }}</li>
                @endforeach
            </ul>
            <div class="flex flex-wrap gap-3 pt-2">
                <a href="{{ $app['url'] }}" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white dark:bg-white dark:text-slate-900">{{ $app['cta'] }}</a>
                <a href="{{ $app['url'] }}/features" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold dark:border-white/10">{{ __('All features') }}</a>
            </div>
            <dl class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 pt-4 text-sm">
                <dt class="text-slate-500 dark:text-white/50">{{ __('Address') }}</dt><dd dir="ltr"><a class="underline" href="{{ $app['url'] }}">{{ $app['host'] }}</a></dd>
                <dt class="text-slate-500 dark:text-white/50">{{ __('Status') }}</dt><dd>{{ ($status['ok'] ?? null) === null ? __('not checked yet') : (($status['ok']) ? __('up') : __('down')) }} · <a class="underline" href="{{ $app['url'] }}/status">{{ $app['host'] }}/status</a></dd>
                @if ($app['repo'])<dt class="text-slate-500 dark:text-white/50">{{ __('Source') }}</dt><dd dir="ltr"><a class="underline" href="{{ $app['repo'] }}">{{ \Illuminate\Support\Str::after($app['repo'], 'github.com/') }}</a> (MIT)</dd>@endif
                <dt class="text-slate-500 dark:text-white/50">{{ __('Help') }}</dt><dd><a class="underline" href="{{ $app['url'] }}/help">{{ __('Help') }}</a> · <a class="underline" href="{{ $app['url'] }}/faq">FAQ</a> · <a class="underline" href="{{ $app['url'] }}/contact">{{ __('Contact') }}</a></dd>
            </dl>
            <p class="pt-4 text-sm"><a class="underline" href="{{ route('home') }}#{{ $app['slug'] }}">{{ __('See all HOP apps') }}</a></p>
        </div>
    </section>
</x-marketing-layout>
