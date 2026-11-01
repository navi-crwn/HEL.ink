@php
    $stories = \App\Support\FamilyStory::all();
@endphp
@push('head')
    <link rel="stylesheet" href="{{ asset('hop-assets/v2/hop-home.css') }}">
    <script src="{{ asset('hop-assets/v2/hop-hero3d.js') }}" defer></script>
    <script src="{{ asset('hop-assets/v2/hop-motion.js') }}" defer></script>
    <script src="{{ asset('hop-assets/v2/hop-home.js') }}" defer></script>
    @vite(['resources/css/home.css', 'resources/js/home.js'])
@endpush
<x-marketing-layout :title="'HEL.ink · '.__('One account. Every HOP app.')" :themed="true" :back="false" :alternates="true">
<div class="hop-home">
    <section class="h-hero" aria-labelledby="home-title">
        <div><span class="h-chip">{{ count($stories) }} {{ __('apps') }} · {{ __('Open source') }}</span><h1 id="home-title">{{ __('One account.') }} <em>{{ __('Every HOP app.') }}</em></h1><p class="h-lead">{{ __('Short links, a page for your links, images and private notes. One free HOP ID connects them all.') }}</p><div class="h-cta"><a class="h-pill dark" href="{{ hub_url('register') }}">{{ __('Create HOP ID') }}</a><a class="h-pill" href="#linkhop">{{ __('See the apps') }}</a></div>
        @foreach ($announcements as $a)<aside><a href="{{ route('announcements.show', $a) }}">{{ $a->title }} ↗</a></aside>@endforeach
        </div><x-home-art preset="hub" :eager="true" />
    </section>
    @php
        $gh = app(\App\Support\GithubStats::class)->all();
        $uptime = app(\App\Support\UptimeStats::class)->all();
        $commits = collect($gh['repos'])->sum('commits');
        $repoCount = count((array) config('hop.github.repos', []));
    @endphp
    <div class="hx-marq" data-hx-marq aria-label="{{ __('What you get') }}"><div>@foreach (['One free account', 'Four apps', 'Short links', 'Link in bio', 'Image hosting', 'Private notes', 'No ads', 'No trackers', 'Open source', 'Self-host if you like'] as $t)<span>{{ __($t) }}</span>@endforeach</div></div>
    <div class="hx-nums" data-hx-nums>
        <div><b data-count="{{ count($stories) }}">{{ count($stories) }}</b><span>{{ __('apps') }}</span></div>
        <div><b data-count="{{ count(\App\Http\Middleware\SetLocale::LOCALES) }}">{{ count(\App\Http\Middleware\SetLocale::LOCALES) }}</b><span>{{ __('languages') }}</span></div>
        <div><b data-count="0">0</b><span>{{ __('ads and trackers') }}</span></div>
        @if ($commits > 0)
            <div><b data-count="{{ $commits }}">{{ number_format($commits) }}</b><span>{{ __('commits in the open') }}</span></div>
        @else
            <div><b data-count="{{ $repoCount }}">{{ $repoCount }}</b><span>{{ __('open source repositories') }}</span></div>
        @endif
    </div>
    @include('partials.home-account')
    <span id="apps" aria-hidden="true"></span>
    @foreach ($stories as $slug => $app)
    <section id="{{ $slug }}" class="h-sec h-app" style="--app-accent: {{ $app['color'] }}" aria-labelledby="{{ $slug }}-title" data-app-section="{{ $slug }}">
        <div class="h-app-copy"><span class="h-tag" data-rv><img src="{{ $app['mark'] }}" alt="" width="28" height="28">{{ $app['name'] }}</span><h2 id="{{ $slug }}-title" data-split><em>{{ $app['kicker'] }}</em></h2><p class="h-sub" data-rv>{{ $app['story'] }}</p>
            <ul class="h-feats">@foreach ($app['steps'] as [$title, $description])<li><span aria-hidden="true">✓</span><span><b>{{ $title }}</b>{{ $description }}</span></li>@endforeach</ul>
            <div class="h-cta"><a class="h-pill dark" href="{{ $app['url'] }}">{{ __('Open :app', ['app' => $app['name']]) }}</a><a class="h-pill" href="{{ $app['url'] }}/#features">{{ __('Learn more') }}</a></div>
        </div><div><x-home-art :preset="$slug" />@include('partials.home-mini-demo')</div>
    </section>
    @endforeach
    @include('partials.home-open')
    @include('partials.home-faq')
    <section class="hx hx-final" aria-labelledby="final-title">
        <div class="hx-wrap"><p class="h-label"><span class="n">05</span> · <span>{{ __('Your next hop') }}</span></p><h2 id="final-title"><span>{{ __('One small hop.') }} <em>{{ __('So many possibilities.') }}</em></span></h2><p class="h-sub"><span>{{ __('Short links, a page for your links, images and private notes. One free HOP ID connects them all.') }}</span></p><div class="h-cta"><a class="h-pill dark" href="{{ hub_url('register') }}">{{ __('Create HOP ID') }}</a><a class="h-pill" href="#linkhop">{{ __('See the apps') }}</a></div></div>
    </section>
</div>
</x-marketing-layout>
