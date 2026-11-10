<x-app-layout>
    <x-slot name="pageTitle">{{ __('Launcher') }} · HOP</x-slot>
    @php
        $user = auth()->user();
        // The first name sits in an italic serif span; "@@NAME@@" keeps it out of the translated sentence.
        $name = '<span class="hop-serif">'.e($dashboard->firstName()).'</span>';
        $greetings = [
            'morning' => str_replace('@@NAME@@', $name, e(__('Good morning, :name', ['name' => '@@NAME@@']))),
            'afternoon' => str_replace('@@NAME@@', $name, e(__('Good afternoon, :name', ['name' => '@@NAME@@']))),
            'evening' => str_replace('@@NAME@@', $name, e(__('Good evening, :name', ['name' => '@@NAME@@']))),
        ];
        $period = $dashboard->period();
        $tone = ['ok' => 'bg-emerald-500', 'problem' => 'bg-rose-500', 'unknown' => 'bg-slate-400'][$summary['state']];
        $settings = route('settings');
        $actionLabels = ['created' => __('Created'), 'updated' => __('Updated'), 'deleted' => __('Deleted'), 'suspended' => __('Suspended'), 'activated' => __('Activated')];
    @endphp

    <div class="min-w-0">
        {{-- Without a saved timezone the server cannot know the hour: the browser picks the greeting. --}}
        <h1 class="page-title" @unless ($user->timezone) x-data x-init="const h = new Date().getHours(); $el.innerHTML = @js($greetings)[h < 11 ? 'morning' : h < 18 ? 'afternoon' : 'evening']" @endunless>{!! $greetings[$period] !!}</h1>
        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-muted" role="status">
            <span class="inline-block h-2 w-2 shrink-0 rounded-full {{ $tone }}" aria-hidden="true"></span>
            <span>
                @if ($summary['state'] === 'ok')
                    {{ trans_choice('{1} :count app running, all normal|[2,*] :count apps running, all normal', $summary['up'], ['count' => $summary['up']]) }}
                @elseif ($summary['state'] === 'problem')
                    {{ __('Problem with :apps', ['apps' => implode(', ', $summary['down'])]) }}
                @else
                    {{ __('Status checks have not run yet') }}
                @endif
            </span>
            <a href="{{ route('status') }}" class="tap underline decoration-dotted underline-offset-2 hover:text-ink">{{ __('Status page') }}</a>
        </p>
    </div>

    <x-flash />
    @foreach ($announcements as $a)
        <a href="{{ route('announcements.show', $a) }}" class="glass flex items-start gap-3 rounded-2xl px-4 py-3 {{ $a->level === 'critical' ? 'border-rose-400/40' : ($a->level === 'warning' ? 'border-amber-400/40' : '') }}">
            <span class="mt-0.5 chip-blue">{{ $a->app_slug ? ucfirst($a->app_slug) : 'HOP' }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-ink">{{ $a->title }}</span>
                <span class="block text-xs text-muted">{{ $a->published_at->diffForHumans() }}</span>
            </span>
        </a>
    @endforeach

    {{-- Reminders the person can hide for a week (App\Support\Reminders). The shared hop-ui.js, or the hub's own
         reminders.js without it, adds the "Hide" button; the announcements above are not reminders and stay. --}}
    @php
        $remindAttrs = 'data-hop-remind-url="'.e(route('reminders.dismiss')).'" data-hop-remind-hide="'.e(__('Hide')).'" data-hop-remind-again="'.e(__('Shows again next week')).'"';
        $showHealth = $health && in_array(\App\Support\Reminders::ACCOUNT_HEALTH, $reminders, true);
    @endphp
    @if (in_array(\App\Support\Reminders::TWO_FACTOR, $reminders, true))
        <section class="glass flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl py-3 pl-4 pr-14" aria-labelledby="remind-2fa-title" data-hop-remind="{{ \App\Support\Reminders::TWO_FACTOR }}" {!! $remindAttrs !!}>
            <div class="min-w-0 flex-1 basis-56">
                <h2 id="remind-2fa-title" class="text-sm font-semibold text-ink">{{ __('Two-factor is off') }}</h2>
                <p class="text-xs text-muted">{{ __('Adds a code from your phone at sign-in') }}</p>
            </div>
            <a href="{{ $settings }}#2fa" class="btn-soft btn-sm whitespace-nowrap">{{ __('Turn on two-factor') }}</a>
        </section>
    @endif
    @if (in_array(\App\Support\Reminders::RECOVERY_CODES, $reminders, true))
        @php $codesLeft = $dashboard->recoveryCodesLeft(); @endphp
        <section class="glass flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border-amber-400/40 py-3 pl-4 pr-14" aria-labelledby="remind-codes-title" data-hop-remind="{{ \App\Support\Reminders::RECOVERY_CODES }}" {!! $remindAttrs !!}>
            <div class="min-w-0 flex-1 basis-56">
                <h2 id="remind-codes-title" class="text-sm font-semibold tabular-nums text-ink">{{ trans_choice('{0} You have no recovery codes left|{1} You have :count recovery code left|[2,*] You have :count recovery codes left', $codesLeft, ['count' => $codesLeft]) }}</h2>
                <p class="text-xs text-muted">{{ __('Each code works once. Make new ones before you need them.') }}</p>
            </div>
            <a href="{{ $settings }}#2fa" class="btn-soft btn-sm whitespace-nowrap">{{ __('Make new codes') }}</a>
        </section>
    @endif

    {{-- My apps --}}
    <section aria-labelledby="apps-title" class="space-y-3">
        <h2 id="apps-title" class="section-title">{{ __('My apps') }}</h2>
        <div class="hop-bento" data-bare data-rv-load>
            @foreach ($apps as $app)
                <x-app-card :app="$app" :state="$status[$app->slug] ?? null">
                    @if (! empty($appNumbers[$app->slug]))
                        <x-slot:stats>
                            <dl class="flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($appNumbers[$app->slug] as $row)
                                    <div class="min-w-0"><dt class="text-[11px] text-muted">{{ __($row['label']) }}</dt><dd class="font-mono text-sm font-semibold tabular-nums text-ink">{{ $row['value'] }}</dd></div>
                                @endforeach
                            </dl>
                        </x-slot:stats>
                    @endif
                </x-app-card>
            @endforeach
        </div>
    </section>

    <div class="hop-bento" data-bare data-rv-load>
        {{-- Account health: the checklist, while a step is open and the person has not hidden it this week.
             Once every step is done it is gone for good. --}}
        @if ($showHealth)
            <section class="glass-card remind-compact min-w-0" aria-labelledby="health-title" data-hop-remind="{{ \App\Support\Reminders::ACCOUNT_HEALTH }}" {!! $remindAttrs !!}>
                <div class="flex items-center gap-4 pr-10">
                    <div class="relative h-16 w-16 shrink-0" role="img" aria-label="{{ __(':done of :total done', ['done' => $health['done'], 'total' => $health['total']]) }}">
                        <svg viewBox="0 0 36 36" class="h-16 w-16 -rotate-90" fill="none" stroke-width="3.4" aria-hidden="true">
                            <circle cx="18" cy="18" r="15.9155" class="text-slate-900/10 dark:text-white/10" stroke="currentColor"/>
                            <circle cx="18" cy="18" r="15.9155" class="{{ $health['percent'] === 100 ? 'text-emerald-500' : 'text-indigo-500' }}" stroke="currentColor" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $health['percent'] }} 100"/>
                        </svg>
                        <span class="absolute inset-0 grid place-items-center font-mono text-sm font-semibold tabular-nums text-ink" aria-hidden="true">{{ $health['done'] }}/{{ $health['total'] }}</span>
                    </div>
                    <div class="min-w-0">
                        <h2 id="health-title" class="section-title">{{ __('Account health') }}</h2>
                        <p class="mt-0.5 text-xs tabular-nums text-muted">{{ __(':done of :total done', ['done' => $health['done'], 'total' => $health['total']]) }}</p>
                    </div>
                </div>
                <ul class="mt-3 space-y-0.5 text-sm">
                    @foreach ($health['steps'] as $step)
                        <li>
                            <a href="{{ $step['url'] }}" class="flex min-h-11 items-start gap-2.5 rounded-lg px-1 py-1.5 {{ $step['done'] ? 'text-muted' : 'text-ink hover:text-indigo-600 dark:hover:text-indigo-300' }}">
                                <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full border {{ $step['done'] ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600' }}" aria-hidden="true">
                                    @if ($step['done'])<svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block {{ $step['done'] ? 'line-through' : 'font-medium' }}">{{ __($step['label']) }}<span class="sr-only"> ({{ $step['done'] ? __('done') : __('to do') }})</span></span>
                                    @unless ($step['done'])
                                        <span class="block text-xs text-muted">{{ __($step['hint']) }}</span>
                                    @endunless
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Security --}}
        <section class="glass-card min-w-0" aria-labelledby="security-title">
            <div class="flex items-center justify-between gap-2">
                <h2 id="security-title" class="section-title">{{ __('Security') }}</h2>
                @if ($user->hasTwoFactor())
                    <span class="chip-green">{{ __('Two-factor is on') }}</span>
                @else
                    <span class="chip-amber">{{ __('Two-factor is off') }}</span>
                @endif
            </div>
            <p class="stat-label mt-3">{{ __('Last sign-ins') }}</p>
            <ul class="mt-1.5 divide-y divide-slate-500/10">
                @forelse ($logins as $login)
                    @php $place = \App\Support\HubDashboard::place($login); @endphp
                    <li class="py-2">
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 truncate text-sm font-medium text-ink">{{ $place ?? __('Unknown location') }}</p>
                            <time class="shrink-0 text-xs text-muted" datetime="{{ $login->logged_in_at->toIso8601String() }}" title="{{ $login->logged_in_at->format('M d, Y H:i') }}">{{ $login->logged_in_at->diffForHumans() }}</time>
                        </div>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted">
                            <span class="min-w-0 truncate">{{ collect([$login->device, $login->browser])->filter()->implode(' · ') ?: __('Unknown device') }}</span>
                            @if ($login->is_proxy)<span class="chip-amber">{{ __('Proxy') }}</span>@endif
                        </p>
                    </li>
                @empty
                    <li class="py-2 text-sm text-muted">{{ __('No sign-ins recorded yet.') }}</li>
                @endforelse
            </ul>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <a href="{{ $settings }}#login" class="btn-outline btn-sm">{{ __("This wasn't me") }}</a>
                <a href="{{ $settings }}#login" class="btn-link btn-sm">{{ __('All sign-ins') }}</a>
                {{-- The status above is a chip, and a chip is not a link: the way to turn it on is a button here. --}}
                @unless ($user->hasTwoFactor())
                    <a href="{{ $settings }}#2fa" class="btn-link btn-sm">{{ __('Turn on two-factor') }}</a>
                @endunless
            </div>
        </section>

        <div class="grid min-w-0 content-start gap-3">
            {{-- Recent activity --}}
            <section class="glass-card min-w-0" aria-labelledby="activity-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="activity-title" class="section-title">{{ __('Recent activity') }}</h2>
                    <a href="{{ $settings }}#activity" class="btn-link btn-sm -mr-1.5">{{ __('View all') }}</a>
                </div>
                <ul class="mt-2 divide-y divide-slate-500/10">
                    @forelse ($activity as $row)
                        <li class="py-2">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 break-words text-sm text-ink">{{ $row->description }}</p>
                                <span class="chip-gray shrink-0">{{ $actionLabels[$row->action] ?? ucfirst($row->action) }}</span>
                            </div>
                            <time class="text-xs text-muted" datetime="{{ $row->created_at->toIso8601String() }}" title="{{ $row->created_at->format('M d, Y H:i') }}">{{ $row->created_at->diffForHumans() }}</time>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-muted">{{ __('No activity yet.') }}</li>
                    @endforelse
                </ul>
            </section>

            {{-- Data and privacy --}}
            <section class="glass-card min-w-0" aria-labelledby="data-title">
                <h2 id="data-title" class="section-title">{{ __('Data and privacy') }}</h2>
                <p class="mt-1 text-xs text-muted">{{ __('Your account data is yours: download a copy, or delete the account for good.') }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <a href="{{ $settings }}#backup" class="btn-outline btn-sm">{{ __('Export my data') }}</a>
                    @unless ($user->isSuperAdmin())
                        <a href="{{ $settings }}#account" class="btn-danger-outline btn-sm">{{ __('Delete account') }}</a>
                    @endunless
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
