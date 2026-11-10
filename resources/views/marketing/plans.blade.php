<x-marketing-layout :title="__('Usage tiers') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('Usage tiers') }}</h1>
        <p class="mt-2 text-slate-600 dark:text-white/70">{{ __('Every HOP app is free. Limits exist only to keep the service healthy for everyone.') }}</p>
        <div class="hop-bento mt-8">
            <div data-spot>
                <x-bento-scene kind="ticks" />
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Guest') }}</h2>
                <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-slate-700 dark:text-white/80">
                    <li>{{ __('Short links from the LinkHop home page, without an account') }}</li>
                    <li>{{ __('Guest links expire after a while') }}</li>
                </ul>
            </div>
            <div data-spot>
                <x-bento-scene kind="ticks" color="#8b5cf6" />
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('HOP ID (free)') }}</h2>
                <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-slate-700 dark:text-white/80">
                    <li>{{ __('Permanent links, bio pages, images and notes') }}</li>
                    <li>{{ __('Analytics, folders, tags, QR codes') }}</li>
                    <li>{{ __('API tokens with scopes') }}</li>
                    <li>{{ __('Fair-use limits per app; see each app\'s help page') }}</li>
                </ul>
            </div>
        </div>
        <p class="mt-6 text-sm text-slate-600 dark:text-white/70">{!! __('If HOP ever adds a paid tier it will be announced on <a class="underline" href=":url">announcements</a> first; existing free features stay free.', ['url' => route('announcements.index')]) !!}</p>
    </section>
</x-marketing-layout>
