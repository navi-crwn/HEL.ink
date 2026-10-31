<x-marketing-layout :title="__('Contact') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('Contact') }}</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p>{{ __('HOP is run by one person, so email is the way in. Replies can take a few days.') }}</p>
            <ul class="list-disc space-y-1 ps-5 text-sm">
                <li>{!! __('General and support: <a class="underline" href="mailto::email">:email</a>', ['email' => config('mail.addresses.support')]) !!}</li>
                <li>{!! __('Abuse, phishing or malware behind a link: <a class="underline" href="mailto::email">:email</a> (or the "Report abuse" link at LinkHop)', ['email' => config('mail.addresses.security')]) !!}</li>
                <li>{!! __('Feedback and ideas: <a class="underline" href="mailto::email">:email</a>', ['email' => config('mail.addresses.feedback')]) !!}</li>
                <li>{!! __('Bugs: an issue on <a class="underline" href="https://github.com/navi-crwn/HEL.ink/issues" rel="noopener">GitHub</a>') !!}</li>
            </ul>
        </div>
    </section>
</x-marketing-layout>
