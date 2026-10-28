<x-marketing-layout title="HOP announcements" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Announcements</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-white/70">Changes to any HOP app are announced here first, as the terms promise.</p>
        <div class="mt-8 space-y-3">
            @forelse ($announcements as $a)
                <a href="{{ route('announcements.show', $a) }}" class="block rounded-2xl border border-slate-200/80 bg-white/70 p-5 transition hover:shadow-md dark:border-white/10 dark:bg-white/3">
                    <p class="text-xs font-semibold uppercase tracking-wider {{ $a->level === 'critical' ? 'text-rose-500' : ($a->level === 'warning' ? 'text-amber-500' : 'text-indigo-500') }}">{{ $a->app_slug ? ucfirst($a->app_slug) : 'HOP' }} · {{ $a->published_at->format('j M Y') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $a->title }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-white/70">{{ \Illuminate\Support\Str::limit(strip_tags($a->body), 200) }}</p>
                </a>
            @empty
                <p class="text-sm text-slate-600 dark:text-white/70">Nothing announced right now.</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $announcements->links() }}</div>
    </section>
</x-marketing-layout>
