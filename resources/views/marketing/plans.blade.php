<x-marketing-layout title="Usage tiers - HOP" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Usage tiers</h1>
        <p class="mt-2 text-slate-600 dark:text-white/70">Every HOP app is free. Limits exist only to keep the service healthy for everyone.</p>
        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3">
                <h2 class="font-semibold text-slate-900 dark:text-white">Guest</h2>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700 dark:text-white/80"><li>Short links from the LinkHop home page, without an account</li><li>Guest links expire after a while</li></ul>
            </div>
            <div class="rounded-3xl border border-indigo-300/60 bg-white/70 p-6 dark:border-indigo-400/30 dark:bg-white/3">
                <h2 class="font-semibold text-slate-900 dark:text-white">HOP ID (free)</h2>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700 dark:text-white/80"><li>Permanent links, bio pages and images</li><li>Analytics, folders, tags, QR codes</li><li>API tokens with scopes</li><li>Fair-use limits per app; see each app's help page</li></ul>
            </div>
        </div>
        <p class="mt-6 text-sm text-slate-600 dark:text-white/70">If HOP ever adds a paid tier it will be announced on <a class="underline" href="{{ route('announcements.index') }}">announcements</a> first; existing free features stay free.</p>
    </section>
</x-marketing-layout>
