<x-marketing-layout title="About HOP" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">About HOP</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p><strong>HOP</strong> stands for Hop Easy Link: a small family of free web apps that share one account, one database and one home page at <strong>hel.ink</strong>.</p>
            <p>It started as a single URL shortener. The link-in-bio pages, the image host and the account system grew around it until each deserved its own app and its own address. The hub you are reading now keeps them together: one sign-in (HOP ID), a launcher, announcements and a status page.</p>
            <ul class="list-disc space-y-1 pl-5">
                <li><strong>LinkHop</strong> (link.hel.ink): short links at hel.ink/l/…, QR codes, click analytics.</li>
                <li><strong>BioHop</strong> (bio.hel.ink): a link-in-bio page at hel.ink/b/… with blocks, themes and stats.</li>
                <li><strong>PixelHop</strong> (p.hel.ink): image hosting with albums and direct links.</li>
            </ul>
            <p>Everything is open source and runs on one small server. There is no paid tier and no tracking beyond what the <a class="underline" href="{{ route('privacy') }}">privacy policy</a> describes.</p>
            <p>Made by one person. Questions and bug reports: <a class="underline" href="{{ route('contact') }}">contact</a>.</p>
        </div>
    </section>
</x-marketing-layout>
