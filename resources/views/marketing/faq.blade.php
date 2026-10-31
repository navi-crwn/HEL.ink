<x-marketing-layout :title="__('FAQ') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('Frequently asked questions') }}</h1>
        <dl class="mt-8 space-y-6 text-slate-700 dark:text-white/80">
            <div>
                <dt class="font-semibold text-slate-900 dark:text-white">{{ __('Is HOP free?') }}</dt>
                <dd class="mt-1 text-sm">{{ __('Yes, every app. There are fair-use limits so one person cannot exhaust the server.') }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-900 dark:text-white">{{ __('Do I need separate accounts for LinkHop, BioHop, PixelHop and NoteHop?') }}</dt>
                <dd class="mt-1 text-sm">{{ __('No. One HOP ID signs you in everywhere. Suspending or deleting it applies to every app.') }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-900 dark:text-white">{{ __('Why did my short link change to hel.ink/l/…?') }}</dt>
                <dd class="mt-1 text-sm">{!! __('Short links and bio pages now have their own prefixes (<code>/l/</code> and <code>/b/</code>) so the apps can live apart. Old links redirect during the transition; the data behind them is unchanged.') !!}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-900 dark:text-white">{{ __('Can someone else have my slug as a bio page?') }}</dt>
                <dd class="mt-1 text-sm">{!! __('Yes: <code>/l/name</code> and <code>/b/name</code> are separate. Pages show the owner\'s name so identity comes from the content, not the slug.') !!}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-900 dark:text-white">{{ __('Where are the APIs?') }}</dt>
                <dd class="mt-1 text-sm">{{ __('At each app (link.hel.ink/api/v1, bio.hel.ink/api/v1). Tokens are created once at the hub and work at every app within their scopes.') }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-900 dark:text-white">{{ __('Can I self-host?') }}</dt>
                <dd class="mt-1 text-sm">{!! __('Yes. The <a class="underline" href="https://github.com/navi-crwn/HEL.ink" rel="noopener">HEL.ink repository</a> explains how to run the hub and the apps on one server behind nginx with a shared database.') !!}</dd>
            </div>
        </dl>
    </section>
</x-marketing-layout>
