<x-app-layout>
    <x-slot name="pageTitle">HOP - API</x-slot>
    <x-page-header title="API tokens" subtitle="One token, every HOP API." />
    <div class="glass space-y-4 rounded-2xl p-5 text-sm text-ink">
        <p>Tokens are created in <a href="{{ route('settings') }}#api" class="underline">Settings → API tokens</a> and sent as a bearer token. Each app checks the token against the same table and refuses calls outside the token's scopes.</p>
        <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100">Authorization: Bearer hlk_…</pre>
        <h3 class="section-title">Where the APIs live</h3>
        <ul class="list-disc space-y-1 pl-5">
            @foreach (\App\Support\HopApps::all() as $app)
                <li><strong>{{ $app->name }}</strong>: <code>{{ $app->url }}/api/v1</code>, documented at <a href="{{ $app->url }}/api-docs" class="underline">{{ parse_url($app->url, PHP_URL_HOST) }}/api-docs</a></li>
            @endforeach
        </ul>
        <h3 class="section-title">Scopes</h3>
        <ul class="list-disc space-y-1 pl-5">
            @foreach (\App\Models\ApiToken::SCOPES as $scope => $what)
                <li><code>{{ $scope }}</code> — {{ $what }}</li>
            @endforeach
        </ul>
        <p class="text-muted">A token with no scopes can do everything its owner can. Rate limit: 100 requests per hour per token at each app.</p>
    </div>
</x-app-layout>
