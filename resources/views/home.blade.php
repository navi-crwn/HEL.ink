@php($stories = \App\Support\FamilyStory::all())
@push('head')
    <link rel="stylesheet" href="{{ asset('hop-assets/v2/hop-home.css') }}">
    <script src="{{ asset('hop-assets/v2/hop-hero3d.js') }}" defer></script>
    <script src="{{ asset('hop-assets/v2/hop-motion.js') }}" defer></script>
    <script src="{{ asset('hop-assets/v2/hop-home.js') }}" defer></script>
@endpush
<x-marketing-layout :title="'HEL.ink · '.__('One account. Every HOP app.')" :themed="true" :back="false" :alternates="true">
<div class="hop-home">
    <section class="h-hero" aria-labelledby="home-title">
        <div><span class="h-chip">{{ count($stories) }} {{ __('apps') }} · {{ __('Open source') }}</span><h1 id="home-title">{{ __('One account.') }} <em>{{ __('Every HOP app.') }}</em></h1><p class="h-lead">{{ __('Short links, a page for your links, images and private notes. One free HOP ID connects them all.') }}</p><div class="h-cta"><a class="h-pill dark" href="{{ hub_url('register') }}">{{ __('Create HOP ID') }}</a><a class="h-pill" href="#linkhop">{{ __('See the apps') }}</a></div>
        @foreach ($announcements as $a)<aside><a href="{{ route('announcements.show', $a) }}">{{ $a->title }} ↗</a></aside>@endforeach
        </div><x-home-art preset="hub" :eager="true" />
    </section>
    <span id="apps" aria-hidden="true"></span>
    @foreach ($stories as $slug => $app)
    <section id="{{ $slug }}" class="h-sec h-app" style="--app-accent: {{ $app['color'] }}" aria-labelledby="{{ $slug }}-title" data-app-section="{{ $slug }}">
        <div class="h-app-copy"><span class="h-tag" data-rv><img src="{{ $app['mark'] }}" alt="" width="28" height="28">{{ $app['name'] }}</span><h2 id="{{ $slug }}-title" data-split><em>{{ $app['kicker'] }}</em></h2><p class="h-sub" data-rv>{{ $app['story'] }}</p>
            <ul class="h-feats">@foreach ($app['steps'] as [$title, $description])<li><span aria-hidden="true">✓</span><span><b>{{ $title }}</b>{{ $description }}</span></li>@endforeach</ul>
            <div class="h-cta"><a class="h-pill dark" href="{{ $app['url'] }}">{{ __('Open :app', ['app' => $app['name']]) }}</a><a class="h-pill" href="{{ $app['url'] }}/#features">{{ __('Learn more') }}</a></div>
        </div><div><x-home-art :preset="$slug" />@include('partials.home-mini-demo')</div>
    </section>
    @endforeach
    <section id="connects" class="h-sec" aria-labelledby="connects-title" data-home-tabs>
        <span class="h-tag">HOP ID</span><h2 id="connects-title" data-split>{{ __('One account.') }} <em>{{ __('Connected by design.') }}</em></h2><p class="h-sub">{{ __('Sign in once. Your apps, password and two-factor protection stay together.') }}</p>
        <div class="h-tabs" role="tablist" aria-label="{{ __('How HOP ID connects') }}">@foreach (['Sign in', 'Security', 'Your apps'] as $label)<button type="button" role="tab" id="connect-tab-{{ $loop->index }}" aria-controls="connect-panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ __($label) }}</button>@endforeach</div>
        @foreach (['Sign in' => 'One account for every HOP app.', 'Security' => 'One password. Optional two-factor protection.', 'Your apps' => 'Choose an app and keep going.'] as $label => $description)
        <div class="h-demo" role="tabpanel" id="connect-panel-{{ $loop->index }}" aria-labelledby="connect-tab-{{ $loop->index }}" data-connect-demo><p>{{ __($description) }}</p><div class="h-cta"><span class="h-chip">HOP ID →</span>@foreach ($stories as $app)<button type="button" data-connect-app="{{ $app['name'] }}" aria-pressed="false"><img src="{{ $app['mark'] }}" alt="" width="24" height="24">{{ $app['name'] }}</button>@endforeach</div><output aria-live="polite">HOP ID</output><p class="h-small">{{ __('Demo only. Everything stays in your browser.') }}</p></div>
        @endforeach
    </section>
    <section class="h-sec" aria-labelledby="open-title"><span class="h-tag">{{ __('Privacy & open source') }}</span><h2 id="open-title" data-split>{{ __('Built in the open.') }} <em>{{ __('Yours to trust.') }}</em></h2><p class="h-sub">{{ __('Free tools, clear choices, and code you can inspect.') }}</p><div class="h-grid">@foreach (['No ads' => 'No advertising business model.', 'Your data' => 'Export your data or delete your account.', 'Open source' => 'Read the code, report an issue, or self-host.'] as $label => $description)<article class="h-demo"><h3>{{ __($label) }}</h3><p>{{ __($description) }}</p></article>@endforeach</div><div class="h-cta"><a class="h-pill" href="{{ route('privacy') }}">{{ __('Privacy') }}</a><a class="h-pill" href="https://github.com/navi-crwn/HEL.ink">GitHub ↗</a><a class="h-pill" href="{{ route('status') }}">{{ __('Status') }}</a></div></section>
    <section class="h-sec" aria-labelledby="faq-title"><span class="h-tag">FAQ</span><h2 id="faq-title" data-split>{{ __('Good questions.') }} <em>{{ __('Simple answers.') }}</em></h2><p class="h-sub">{{ __('Start here, then make something yours.') }}</p>
        @foreach (['Is HOP free?' => 'Yes. The apps are free and open source.', 'Do I need an account?' => 'One HOP ID works across all four apps. Some tools also work without signing in.', 'Can the server read my note?' => 'Never. The cryptographic key is in the URL hash fragment, which browsers never send to the server.', 'Can I self-host?' => 'Yes. Read the installation guide in each repository.', 'Where can I get help?' => 'Each app has a help page. You can also report issues on GitHub.'] as $question => $answer)<details><summary>{{ __($question) }}</summary><p>{{ __($answer) }}</p></details>@endforeach
        <div class="h-final"><h2>{{ __('One small hop.') }} <em>{{ __('So many possibilities.') }}</em></h2><div class="h-cta"><a class="h-pill dark" href="{{ hub_url('register') }}">{{ __('Create HOP ID') }}</a><a class="h-pill" href="#linkhop">{{ __('See the apps') }}</a></div></div>
    </section>
</div>
</x-marketing-layout>
