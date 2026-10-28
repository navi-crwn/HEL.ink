@php
    $footerBlocks = [
        ['title' => 'HOP', 'items' => [
            ['label' => 'About', 'route' => route('about')],
            ['label' => 'Products', 'route' => route('products')],
            ['label' => 'Status', 'route' => route('status')],
            ['label' => 'Announcements', 'route' => route('announcements.index')],
        ]],
        ['title' => 'Support', 'items' => [
            ['label' => 'Help center', 'route' => route('help')],
            ['label' => 'FAQ', 'route' => route('faq')],
            ['label' => 'Contact', 'route' => route('contact')],
            ['label' => 'Usage tiers', 'route' => route('plans')],
        ]],
        ['title' => 'Policies', 'items' => [
            ['label' => 'Privacy policy', 'route' => route('privacy')],
            ['label' => 'Terms of service', 'route' => route('terms')],
        ]],
        ['title' => 'Apps', 'items' => array_map(fn ($a) => ['label' => $a->name, 'route' => $a->url, 'external' => true], \App\Support\HopApps::all())],
    ];
@endphp
<footer class="mt-16 px-3 pb-6 sm:px-5">
    <div class="mx-auto max-w-6xl rounded-3xl border border-slate-200/80 bg-white/60 p-6 text-sm text-slate-600 backdrop-blur-xl dark:border-white/10 dark:bg-white/3 dark:text-white/60 sm:p-8">
        <div class="grid grid-cols-2 gap-8 lg:grid-cols-5">
            <div class="col-span-2 lg:col-span-1">
                <x-wordmark class="h-10 w-auto" />
                <p class="mt-3 text-[13px] leading-relaxed">Hop Easy Link. One hop to every app. Free and open source.</p>
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
        <p class="mt-8 border-t border-slate-200 pt-5 text-xs text-slate-500 dark:border-white/10 dark:text-white/60">© {{ date('Y') }} HOP · hel.ink</p>
    </div>
</footer>
