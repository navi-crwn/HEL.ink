{{-- Home: "Built in the open. Yours to trust." Repo numbers and uptime are rendered here from the
     stored data (no network on a page view, no JavaScript needed); public/js/home-open.js only adds
     tab switching and an optional bar animation. Include inside .hop-home. --}}
@php
    $stories = $stories ?? \App\Support\FamilyStory::all();
    $gh = $githubStats ?? app(\App\Support\GithubStats::class)->all();
    $uptime = $uptimeStats ?? app(\App\Support\UptimeStats::class)->all();
    $repoData = collect($gh['repos'])->keyBy('repo');
    $repoNames = collect((array) config('hop.github.repos', []));
    $orgUrl = (string) config('hop.github.org_url');
    $fmtNum = fn (?int $n) => $n === null ? '–' : number_format($n);
    $fmtDate = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->translatedFormat('j M Y') : '–';
    $fmtPct = fn (?float $p) => $p === null ? '–' : rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.').'%';
    $asset = fn (string $path) => asset($path).'?v='.substr((string) @md5_file(public_path($path)), 0, 8);
    $measured = collect($uptime['apps'])->max('days_measured') ?? 0;
    $hasUptime = collect($uptime['apps'])->contains(fn ($a) => $a['uptime'] !== null);
@endphp
@push('head')
    <link rel="stylesheet" href="{{ $asset('css/home-open.css') }}">
    <script src="{{ $asset('js/home-open.js') }}" defer></script>
@endpush
<section id="open" class="h-sec ho" aria-labelledby="open-title" data-home-open>
    <span class="h-tag">{{ __('Privacy & open source') }}</span>
    <h2 id="open-title" data-split>{{ __('Built in the open.') }} <em>{{ __('Yours to trust.') }}</em></h2>
    <p class="h-sub">{{ __('Free tools, clear choices, and code you can inspect.') }}</p>

    @if ($repoData->isEmpty())
        <div class="ho-box ho-empty" data-ho-empty="github">
            <p>{{ __('Repository numbers are not loaded yet. The code is public on GitHub.') }}</p>
            <ul class="ho-links">@foreach ($repoNames as $repo)<li><a class="h-badge" href="https://github.com/{{ $repo }}">{{ $repo }} ↗</a></li>@endforeach</ul>
        </div>
    @else
        <div class="ho-box" data-ho-repos>
            <div class="h-tabs ho-tabs" role="tablist" aria-label="{{ __('Repositories') }}" hidden>
                @foreach ($repoNames as $repo)
                    @php($short = \Illuminate\Support\Str::after($repo, '/'))
                    <button type="button" role="tab" id="open-tab-{{ $loop->index }}" aria-controls="open-panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}">{{ $short }}</button>
                @endforeach
            </div>
            @foreach ($repoNames as $repo)
                @php($r = $repoData->get($repo))
                <div class="ho-panel" role="tabpanel" id="open-panel-{{ $loop->index }}" aria-labelledby="open-tab-{{ $loop->index }}" data-repo="{{ $repo }}">
                    <h3 class="ho-repo"><a href="https://github.com/{{ $repo }}">{{ $repo }} <span aria-hidden="true">↗</span></a></h3>
                    @if (! $r)
                        <p class="ho-empty-line">{{ __('Numbers for this repository are not loaded yet.') }}</p>
                    @else
                        @if ($r['languages'])
                            <div class="ho-bar" role="img" aria-label="{{ __('Languages') }}: {{ collect($r['languages'])->map(fn ($l) => ($l['name'] === 'Other' ? __('Other') : $l['name']).' '.$l['percent'].'%')->implode(', ') }}">
                                @foreach ($r['languages'] as $l)<span class="l{{ $l['name'] === 'Other' ? 'x' : $loop->index }}" style="--w:{{ $l['percent'] }}"></span>@endforeach
                            </div>
                            <ul class="ho-legend" dir="ltr">@foreach ($r['languages'] as $l)<li><i class="l{{ $l['name'] === 'Other' ? 'x' : $loop->index }}" aria-hidden="true"></i>{{ $l['name'] === 'Other' ? __('Other') : $l['name'] }} <b>{{ $l['percent'] }}%</b></li>@endforeach</ul>
                        @endif
                        <dl class="ho-stats">
                            <div><dt>{{ __('Latest release') }}</dt><dd dir="ltr">{{ $r['version'] ?? __('None yet') }}@if ($r['version_date'])<small>{{ $fmtDate($r['version_date']) }}</small>@endif</dd></div>
                            <div><dt>{{ __('Commits') }}</dt><dd dir="ltr">{{ $fmtNum($r['commits']) }}</dd></div>
                            <div><dt>{{ __('Releases') }}</dt><dd dir="ltr">{{ $fmtNum($r['releases']) }}</dd></div>
                            <div><dt>{{ __('Contributors') }}</dt><dd dir="ltr">{{ $fmtNum($r['contributors']) }}</dd></div>
                            <div><dt>{{ __('Stars') }}</dt><dd dir="ltr">{{ $fmtNum($r['stars']) }}</dd></div>
                            <div><dt>{{ __('Last push') }}</dt><dd><time datetime="{{ $r['pushed_at'] }}">{{ $fmtDate($r['pushed_at']) }}</time></dd></div>
                            <div><dt>{{ __('Since') }}</dt><dd><time datetime="{{ $r['created_at'] }}">{{ $fmtDate($r['created_at']) }}</time></dd></div>
                        </dl>
                        @if ($r['weeks'])
                            @php($max = max(1, ...$r['weeks']))
                            <figure class="ho-weeks-fig">
                                <div class="ho-weeks" role="img" aria-label="{{ __('Commits per week, last 52 weeks') }}" dir="ltr">
                                    @foreach ($r['weeks'] as $n)<i @class(['z' => $n === 0]) style="--h:{{ max(4, (int) round($n * 100 / $max)) }}%;--i:{{ $loop->index }}" title="{{ $n }}"></i>@endforeach
                                </div>
                                <figcaption><b dir="ltr">{{ number_format(array_sum($r['weeks'])) }}</b> {{ __('commits in the last year') }}</figcaption>
                            </figure>
                        @endif
                    @endif
                </div>
            @endforeach
            @if ($gh['fetched_at'])<p class="h-small ho-updated">{{ __('Numbers from GitHub. Updated :date.', ['date' => $fmtDate($gh['fetched_at'])]) }}</p>@endif
        </div>
    @endif

    {{-- The apps and the uptime share one row (half and half); one column up to 1024 px. --}}
    <div class="hop-bento ho-row">
        <div id="apps-list" class="ho-box ho-apps" role="region" aria-labelledby="apps-list-title" data-spot>
            <h3 id="apps-list-title">{{ __('The HOP apps') }}</h3>
            <ul>@foreach ($stories as $slug => $app)<li><img src="{{ $app['mark'] }}" alt="" width="36" height="36"><div><b>{{ $app['name'] }}</b><span>{{ $app['kicker'] }}</span></div><code>{{ parse_url($app['url'], PHP_URL_HOST) }}</code><a class="h-pill" href="{{ $app['url'] }}">{{ __('Open :app', ['app' => $app['name']]) }}</a></li>@endforeach</ul>
        </div>
        <div class="ho-box ho-uptime" data-ho-uptime data-spot>
            <h3>{{ __('Uptime, last 30 days') }}</h3>
            @if (! $hasUptime)
                <p class="ho-empty-line" data-ho-empty="uptime">{{ __('Uptime is measured every minute. The first numbers will show here soon.') }}</p>
            @else
                <ul class="ho-up-list">
                    @foreach ($uptime['apps'] as $a)
                        <li>
                            <span class="ho-up-name">{{ $a['name'] }}</span>
                            <span class="ho-strip" role="img" aria-label="{{ __(':app: :percent uptime in the last 30 days', ['app' => $a['name'], 'percent' => $fmtPct($a['uptime'])]) }}" dir="ltr">
                                @foreach ($a['days'] as $d)<i class="{{ $d['status'] }}" title="{{ $d['date'] }}: {{ $d['uptime'] === null ? __('No data') : $fmtPct($d['uptime']) }}"></i>@endforeach
                            </span>
                            <b class="ho-up-pct" dir="ltr">{{ $fmtPct($a['uptime']) }}</b>
                        </li>
                    @endforeach
                </ul>
                @if ($measured < $uptime['days'])<p class="h-small">{{ __(':days of :total days measured so far.', ['days' => $measured, 'total' => $uptime['days']]) }}</p>@endif
            @endif
        </div>
    </div>

    <div class="ho-cards">
        <a class="ho-card" href="{{ route('privacy') }}">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 3 8.2 7 10 4-1.8 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
            <h3>{{ __('Privacy') }}</h3>
            <p>{{ __('Export your data or delete your account.') }}</p>
            <span class="ho-go">{{ __('Read the privacy policy') }} <span aria-hidden="true">→</span></span>
        </a>
        <a class="ho-card" href="{{ $orgUrl }}?tab=repositories">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 8-4 4 4 4"/><path d="m16 8 4 4-4 4"/><path d="m13.5 5-3 14"/></svg>
            <h3>GitHub</h3>
            <p>{{ __('Read the code, report an issue, or self-host.') }}</p>
            <span class="ho-go">{{ __('Browse all repositories') }} <span aria-hidden="true">↗</span></span>
        </a>
        <a class="ho-card" href="{{ route('status') }}">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg>
            <h3>{{ __('Status') }}</h3>
            <p>{{ __('Every app is checked once a minute.') }}</p>
            <span class="ho-go">{{ __('Open the status page') }} <span aria-hidden="true">→</span></span>
        </a>
    </div>
</section>
