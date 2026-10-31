@php
    $footerBlocks = [
        ['title' => 'HOP', 'items' => [
            ['label' => __('About'), 'route' => route('about')],
            ['label' => __('How it works'), 'route' => route('how-it-works')],
            ['label' => __('Security'), 'route' => route('security')],
            ['label' => __('Open source'), 'route' => route('open-source')],
            ['label' => __('Status'), 'route' => route('status')],
            ['label' => __('Announcements'), 'route' => route('announcements.index')],
        ]],
        ['title' => __('Support'), 'items' => [
            ['label' => __('How to sign up'), 'route' => route('signup')],
            ['label' => __('Help'), 'route' => route('help')],
            ['label' => __('FAQ'), 'route' => route('faq')],
            ['label' => __('Contact'), 'route' => route('contact')],
        ]],
        ['title' => __('Policies'), 'items' => [
            ['label' => __('Privacy policy'), 'route' => route('privacy')],
            ['label' => __('Terms of service'), 'route' => route('terms')],
            ['label' => __('Usage tiers'), 'route' => route('plans')],
        ]],
        ['title' => __('Apps'), 'items' => array_values(array_map(fn ($a) => ['label' => $a['name'], 'route' => app_page_url($a['slug'])], \App\Support\FamilyStory::all()))],
    ];
@endphp
<footer class="mt-16 px-3 pb-6 sm:px-5">
    <div class="mx-auto max-w-6xl rounded-3xl border border-slate-200/80 bg-white/60 p-6 text-sm text-slate-600 backdrop-blur-xl dark:border-white/10 dark:bg-white/3 dark:text-white/60 sm:p-8">
        <div class="grid grid-cols-2 gap-8 lg:grid-cols-5">
            <div class="col-span-2 lg:col-span-1">
                <x-wordmark class="h-10 w-auto" />
                <p class="mt-3 text-[13px] leading-relaxed">Hop Easy Link. {{ __('One hop to every app.') }} {{ __('Free and open source.') }}</p>
                <p class="mt-3"><a href="https://github.com/navi-crwn/HEL.ink" class="transition hover:text-slate-900 dark:hover:text-white" target="_blank" rel="noopener">github.com/navi-crwn</a></p>
            </div>
            @foreach ($footerBlocks as $block)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-white/60">{{ $block['title'] }}</p>
                    <ul class="mt-3 space-y-2 text-[13px]">
                        @foreach ($block['items'] as $item)
                            <li><a href="{{ $item['route'] }}" @if (! empty($item['external'])) target="_blank" rel="noopener" @endif class="transition hover:text-slate-900 dark:hover:text-white">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5 text-xs text-slate-500 dark:border-white/10 dark:text-white/60">
            <p>© {{ date('Y') }} HOP · hel.ink · MIT License</p>
            <nav aria-label="{{ __('Language') }}" class="flex flex-wrap gap-x-3 gap-y-1">
                @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $name)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" @class(['hover:text-slate-900 dark:hover:text-white', 'font-semibold text-slate-800 dark:text-white' => app()->getLocale() === $code])>{{ $name }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</footer>
