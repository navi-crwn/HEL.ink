<x-marketing-layout :title="__('About HOP') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('About HOP') }}</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p>{!! __('<strong>HOP</strong> stands for Hop Easy Link: a small family of free web apps that share one account, one database and one home page at <strong>hel.ink</strong>.') !!}</p>
            <p>{{ __('It started as a single URL shortener. The link-in-bio pages, the image host, and encrypted notes grew around it until each deserved its own app and its own address. The hub you are reading now keeps them together: one sign-in (HOP ID), a launcher, announcements and a status page.') }}</p>
            <ul class="list-disc space-y-1 ps-5">
                <li>{!! __('<strong>LinkHop</strong> (link.hel.ink): short links at hel.ink/l/…, QR codes, click analytics.') !!}</li>
                <li>{!! __('<strong>BioHop</strong> (bio.hel.ink): a link-in-bio page at hel.ink/b/… with blocks, themes and stats.') !!}</li>
                <li>{!! __('<strong>PixelHop</strong> (pixel.hel.ink, shared links at p.hel.ink): image hosting with albums and direct links.') !!}</li>
                <li>{!! __('<strong>NoteHop</strong> (note.hel.ink, short links at n.hel.ink): end-to-end encrypted notes.') !!}</li>
            </ul>
            <p>{!! __('Everything is open source and runs on one small server. There is no paid tier and no tracking beyond what the <a class="underline" href=":url">privacy policy</a> describes.', ['url' => route('privacy')]) !!}</p>
            <p>{!! __('Made by one person. Questions and bug reports: <a class="underline" href=":url">contact</a>.', ['url' => route('contact')]) !!}</p>
        </div>
    </section>
</x-marketing-layout>
