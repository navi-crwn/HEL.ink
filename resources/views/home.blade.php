<x-marketing-layout title="HEL.ink - Hop Easy Link" :themed="true">
    <section class="relative mx-auto max-w-6xl px-4 pb-10 pt-16 sm:px-6 sm:pt-24">
        {{-- The floating HOP tiles keep to the empty right half, clear of the copy. --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 w-5/12 max-lg:hidden" aria-hidden="true">
            <x-hop::float mode="section" />
        </div>
        <div class="relative z-10 max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-300">Hop Easy Link</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl dark:text-white">One hop to every app.</h1>
            <p class="mt-5 text-lg text-slate-600 dark:text-white/70">hel.ink is home to the HOP family: short links with LinkHop, a link-in-bio page with BioHop, image hosting with PixelHop. One free account, one sign-in, every app.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ hub_url('register') }}" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-white/90">Create your HOP ID</a>
                <a href="{{ hub_url('login') }}" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-900/5 dark:border-white/10 dark:text-white/80 dark:hover:bg-white/10">Sign in</a>
            </div>
        </div>
    </section>
    @foreach ($announcements as $a)
        <section class="mx-auto max-w-6xl px-4 sm:px-6">
            <a href="{{ route('announcements.show', $a) }}" class="block rounded-2xl border border-amber-300/60 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100">
                <strong>{{ $a->title }}</strong> · {{ $a->published_at->format('j M Y') }}
            </a>
        </section>
    @endforeach
    <section id="apps" class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @php
            // Official app marks from the HOP asset manifest (public/hop-assets).
            $marks = collect(\Hop\Ui\Assets::family())->mapWithKeys(fn ($a) => [$a['slug'] => $a['icons']['mark']['url']]);
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($apps as $app)
                @php $s = $status[$app->slug] ?? null; @endphp
                <a href="{{ $app->url }}" class="group rounded-3xl border border-slate-200/80 bg-white/70 p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-900/5 dark:border-white/10 dark:bg-white/3">
                    <div class="flex items-center justify-between">
                        @if ($marks->has($app->slug))
                            <img src="{{ $marks[$app->slug] }}" alt="" width="40" height="40" class="h-10 w-10 rounded-xl">
                        @else
                            <svg class="h-7 w-7 text-slate-900 dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ \Hop\Ui\Apps::icon($app->icon) }}"/></svg>
                        @endif
                        @if (($s['ok'] ?? null) === false)
                            <span class="rounded-full bg-rose-500/15 px-2 py-0.5 text-[11px] font-semibold text-rose-600 dark:text-rose-300">down</span>
                        @elseif (($s['ok'] ?? null) === true)
                            <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-300">up</span>
                        @endif
                    </div>
                    <h2 class="mt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ $app->name }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-white/70">{{ $app->tagline }}</p>
                    <p class="mt-4 text-xs font-semibold text-indigo-600 group-hover:underline dark:text-indigo-300">{{ parse_url($app->url, PHP_URL_HOST) }}{{ $app->publicPrefix ? ' · '.public_host().$app->publicPrefix.'…' : '' }}</p>
                </a>
            @endforeach
        </div>
    </section>
    <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
        <div class="grid gap-6 rounded-3xl border border-slate-200/80 bg-white/60 p-8 md:grid-cols-3 dark:border-white/10 dark:bg-white/3">
            <div><h3 class="font-semibold text-slate-900 dark:text-white">One HOP ID</h3><p class="mt-1 text-sm text-slate-600 dark:text-white/70">Password or Google, two-factor, one profile shared by every app. Manage it at <a class="underline" href="{{ hub_url() }}">hub.hel.ink</a>.</p></div>
            <div><h3 class="font-semibold text-slate-900 dark:text-white">Free and open source</h3><p class="mt-1 text-sm text-slate-600 dark:text-white/70">Every HOP app is free to use and its code is public. Run the whole family on one server if you like.</p></div>
            <div><h3 class="font-semibold text-slate-900 dark:text-white">Honest status</h3><p class="mt-1 text-sm text-slate-600 dark:text-white/70">A watchdog checks each app every minute. See the <a class="underline" href="{{ route('status') }}">status page</a>.</p></div>
        </div>
    </section>
</x-marketing-layout>
