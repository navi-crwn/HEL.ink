@props([
    'app' => 'hub',
    'name' => 'HEL.ink',
    'tagline' => 'One hop to every app.',
    'repo' => 'https://github.com/navi-crwn/HEL.ink',
    'featuresUrl' => '/features',
    'statusUrl' => '/status',
    'abuseUrl' => 'https://link.hel.ink/report-abuse',
    'languages' => true,
])
@php
    $family = \Hop\Ui\Assets::family($app);
    $locales = [
        'en' => 'English',
        'id' => 'Bahasa Indonesia',
        'zh' => '中文',
        'es' => 'Español',
        'ar' => 'العربية',
        'pt' => 'Português',
        'fr' => 'Français',
        'ja' => '日本語',
    ];
    $currentLocale = app()->getLocale();
@endphp
<footer {{ $attributes->merge(['class' => 'hop-footer border-t border-slate-200/80 bg-slate-50/50 py-12 text-sm dark:border-white/10 dark:bg-slate-950/40']) }}>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4 lg:gap-12">
            {{-- Column 1: App --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">{{ $name }}</h3>
                <ul class="space-y-2 text-slate-600 dark:text-slate-400">
                    <li><a href="/about" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('About') }}</a></li>
                    @if ($featuresUrl)
                        <li><a href="{{ $featuresUrl }}" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Features') }}</a></li>
                    @endif
                    <li><a href="{{ $statusUrl }}" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Status') }}</a></li>
                    <li><a href="/changelog" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Changelog') }}</a></li>
                    @if ($repo)
                        <li><a href="{{ $repo }}" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-900 dark:hover:text-white">GitHub</a></li>
                    @endif
                </ul>
            </div>

            {{-- Column 2: Support --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Support') }}</h3>
                <ul class="space-y-2 text-slate-600 dark:text-slate-400">
                    <li><a href="/help" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Help') }}</a></li>
                    <li><a href="/faq" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('FAQ') }}</a></li>
                    <li><a href="/contact" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Contact') }}</a></li>
                    <li><a href="{{ $abuseUrl }}" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Report abuse') }}</a></li>
                </ul>
            </div>

            {{-- Column 3: Policies --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Policies') }}</h3>
                <ul class="space-y-2 text-slate-600 dark:text-slate-400">
                    <li><a href="/terms" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Terms') }}</a></li>
                    <li><a href="/privacy" class="transition hover:text-slate-900 dark:hover:text-white">{{ __('Privacy') }}</a></li>
                </ul>
            </div>

            {{-- Column 4: HOP Family (from manifest) --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('HOP Family') }}</h3>
                <ul class="space-y-2 text-slate-600 dark:text-slate-400">
                    @foreach ($family as $fapp)
                        <li>
                            @if ($fapp['current'])
                                <span class="font-medium text-slate-900 dark:text-white" aria-current="page">{{ $fapp['name'] }}</span>
                            @else
                                <a href="{{ $fapp['url'] }}" class="transition hover:text-slate-900 dark:hover:text-white">{{ $fapp['name'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Bottom row: Language & License --}}
        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-slate-200/80 pt-8 sm:flex-row dark:border-white/10">
            @if ($languages)
                <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ __('Language') }}:</span>
                    @foreach ($locales as $code => $label)
                        <a href="?lang={{ $code }}" @class(['transition hover:text-slate-900 dark:hover:text-white', 'font-bold text-slate-900 underline underline-offset-2 dark:text-white' => $currentLocale === $code]) hreflang="{{ $code }}">{{ $label }}</a>
                        @if (! $loop->last) <span class="opacity-40" aria-hidden="true">·</span> @endif
                    @endforeach
                </div>
            @endif
            <p class="text-center font-mono text-xs text-slate-500 dark:text-slate-400">
                &copy; {{ date('Y') }} navi-crwn · {{ __('Open source under MIT License') }}
            </p>
        </div>
    </div>
</footer>
