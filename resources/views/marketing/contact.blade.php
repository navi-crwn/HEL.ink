<x-marketing-layout title="Contact - HOP" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Contact</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p>HOP is run by one person, so email is the way in. Replies can take a few days.</p>
            <ul class="list-disc space-y-1 pl-5 text-sm">
                <li>General and support: <a class="underline" href="mailto:{{ config('mail.addresses.support') }}">{{ config('mail.addresses.support') }}</a></li>
                <li>Abuse, phishing or malware behind a link: <a class="underline" href="mailto:{{ config('mail.addresses.security') }}">{{ config('mail.addresses.security') }}</a> (or the "Report abuse" link at LinkHop)</li>
                <li>Feedback and ideas: <a class="underline" href="mailto:{{ config('mail.addresses.feedback') }}">{{ config('mail.addresses.feedback') }}</a></li>
                <li>Bugs: an issue on <a class="underline" href="https://github.com/navi-crwn/HEL.ink/issues">GitHub</a></li>
            </ul>
        </div>
    </section>
</x-marketing-layout>
