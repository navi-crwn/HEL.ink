<x-marketing-layout :title="__('The HOP apps') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('The HOP apps') }}</h1>
        <p class="mt-2 text-slate-600 dark:text-white/70">{{ __('Each app does one thing. Your HOP ID works in all of them.') }}</p>
        <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach (\App\Support\FamilyStory::all() as $app)
                <div class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <img src="{{ $app['mark'] }}" alt="" width="40" height="40" class="h-10 w-10 rounded-xl">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ $app['name'] }}</h2>
                                <p class="text-xs font-semibold uppercase tracking-wider" style="color: {{ $app['color'] }}">{{ $app['kicker'] }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 dark:text-white/70">{{ $app['story'] }}</p>
                        <ul class="mt-4 space-y-1 text-sm text-slate-700 dark:text-white/80 list-disc ps-5">
                            @foreach ($app['features'] as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <a href="{{ $app['url'] }}" class="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                        {{ $app['cta'] }} <span aria-hidden="true" class="inline-block rtl:-scale-x-100">&rarr;</span>
                    </a>
                </div>
            @endforeach
        </div>
    </section>
</x-marketing-layout>
