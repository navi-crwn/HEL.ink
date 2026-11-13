<x-app-layout>
    <x-slot name="pageTitle">API · HOP</x-slot>
    @php
        $apps = \App\Support\HopApps::all();
        $withApi = \App\Http\Controllers\ApiTokenController::hubTokenApps($apps);
        $ownKeys = \App\Http\Controllers\ApiTokenController::ownKeyApps($apps);
        $withoutApi = array_values(array_filter($apps, fn ($a) => ! config('hop.api_docs.'.$a->slug)));
        $scopes = \App\Http\Controllers\ApiTokenController::availableScopes(array_map(fn ($a) => $a->slug, $withApi));
        $reserved = array_diff_key(\App\Models\ApiToken::SCOPES, $scopes, array_flip(\App\Http\Controllers\ApiTokenController::ownKeyScopes()));
    @endphp
    <x-page-header :title="__('API tokens')" :subtitle="__('Hub tokens (hlk_…) are for :apps.', ['apps' => implode(', ', array_map(fn ($a) => $a->name, $withApi))])" />
    <div class="glass space-y-4 rounded-2xl p-5 text-sm text-ink">
        <p>{!! __('Hub tokens are created in :settings and sent as a bearer token. The apps listed here check them and refuse calls outside the token\'s scopes.', ['settings' => '<a href="'.route('settings').'#api" class="underline">'.e(__('Settings → API tokens')).'</a>']) !!}</p>
        <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100">Authorization: Bearer hlk_…</pre>
        <h3 class="section-title">{{ __('Where the APIs live') }}</h3>
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($withApi as $app)
                @php($api = config('hop.api_docs.'.$app->slug))
                <li><strong>{{ $app->name }}</strong>: <code>{{ $app->url.$api['base'] }}</code>, {!! __('documented at :link', ['link' => '<a href="'.e($app->url.$api['docs']).'" class="underline">'.e(parse_url($app->url, PHP_URL_HOST).$api['docs']).'</a>']) !!}</li>
            @endforeach
            @if ($withoutApi)
                <li>{{ __(':apps: no token API yet.', ['apps' => implode(', ', array_map(fn ($a) => $a->name, $withoutApi))]) }}</li>
            @endif
        </ul>
        @if ($ownKeys)
            <h3 class="section-title">{{ __('Apps with their own keys') }}</h3>
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($ownKeys as $app)
                    @php($api = config('hop.api_docs.'.$app->slug))
                    <li>{!! __(':app has its own keys (:prefix…), made at :keys and documented at :docs. A hub token (hlk_…) is not accepted there.', [
                        'app' => '<strong>'.e($app->name).'</strong>',
                        'prefix' => e($api['own_keys']['prefix']),
                        'keys' => '<a href="'.e($app->url.$api['own_keys']['path']).'" class="underline">'.e(parse_url($app->url, PHP_URL_HOST).$api['own_keys']['path']).'</a>',
                        'docs' => '<a href="'.e($app->url.$api['docs']).'" class="underline">'.e(parse_url($app->url, PHP_URL_HOST).$api['docs']).'</a>',
                    ]) !!}</li>
                @endforeach
            </ul>
        @endif
        <h3 class="section-title">{{ __('Scopes') }}</h3>
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($scopes as $scope => $what)
                <li><code>{{ $scope }}</code> — {{ __($what) }}</li>
            @endforeach
        </ul>
        @if ($reserved)
            <p class="text-muted">{{ __('Reserved for apps that have no token API yet: :scopes.', ['scopes' => implode(', ', array_keys($reserved))]) }}</p>
        @endif
        <p class="text-muted">{{ __('A token with no scopes can do everything its owner can. A new token may make :count requests per hour at each app; the limit of each token is shown in its row in Settings.', ['count' => \App\Http\Controllers\ApiTokenController::RATE_LIMIT]) }}</p>
    </div>
</x-app-layout>
