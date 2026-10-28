<x-app-layout>
    <x-slot name="pageTitle">HOP - Launcher</x-slot>
    <x-page-header title="Hop in" subtitle="One account, every app. Open an app and you are signed in." />
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
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($apps as $app)
            <x-app-card :app="$app" :state="$status[$app->slug] ?? null" />
        @endforeach
    </div>
    <p class="text-xs text-muted">Something off? See the <a href="{{ route('status') }}" class="underline">status page</a>. Profile, password and 2FA live in <a href="{{ route('settings') }}" class="underline">Settings</a>.</p>
</x-app-layout>
