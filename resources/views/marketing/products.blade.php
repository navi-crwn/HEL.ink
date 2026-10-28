<x-marketing-layout title="HOP apps" :themed="true">
    <section class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">The HOP apps</h1>
        <p class="mt-2 text-slate-600 dark:text-white/70">Each app does one thing. Your HOP ID works in all of them.</p>
        <div class="mt-8 grid gap-6 md:grid-cols-3">
            @foreach (\App\Support\HopApps::all() as $app)
                <div class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ $app->name }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-white/70">{{ $app->tagline }}</p>
                    <ul class="mt-4 space-y-1 text-sm text-slate-700 dark:text-white/80">
                        @if ($app->slug === 'linkhop')
                            <li>Custom slugs at hel.ink/l/your-slug</li><li>QR codes, password and expiry</li><li>Click analytics by country and device</li><li>Folders, tags, API</li>
                        @elseif ($app->slug === 'biohop')
                            <li>One page at hel.ink/b/you</li><li>Link, social, embed and text blocks</li><li>Themes and colours</li><li>Click stats per block</li>
                        @elseif ($app->slug === 'pixelhop')
                            <li>Drag-and-drop uploads</li><li>Albums and direct links</li><li>Sign in with your HOP ID</li>
                        @endif
                    </ul>
                    <a href="{{ $app->url }}" class="mt-5 inline-block text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-300">Open {{ $app->name }} →</a>
                </div>
            @endforeach
        </div>
    </section>
</x-marketing-layout>
