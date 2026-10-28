<x-marketing-layout :title="$announcement->title.' - HOP'" :themed="true">
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <a href="{{ route('announcements.index') }}" class="text-xs text-slate-500 hover:underline dark:text-white/60">← All announcements</a>
        <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-indigo-500">{{ $announcement->app_slug ? ucfirst($announcement->app_slug) : 'HOP' }} · {{ $announcement->published_at?->format('j M Y') ?? 'draft' }}</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ $announcement->title }}</h1>
        <div class="prose prose-slate mt-6 max-w-none text-slate-700 dark:text-white/80">{!! nl2br(e($announcement->body)) !!}</div>
    </article>
</x-marketing-layout>
