<x-marketing-layout :title="__('Security').' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ __('Security') }}</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p>{{ __('What HOP does to keep accounts and visitors safe, and how to tell us when something is wrong.') }}</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Accounts') }}</h2>
            <ul class="list-disc space-y-1 ps-5">
                <li>{{ __('Passwords are stored as slow hashes, never in plain text.') }}</li>
                <li>{{ __('Two-factor codes (TOTP) and a login history with country and device.') }}</li>
                <li>{!! __('Sign-in to each app with short-lived, single-use tickets (see <a class="underline" href=":url">how it works</a>).', ['url' => route('how-it-works')]) !!}</li>
                <li>{{ __('Rate limits on sign-in, sign-up and password reset, and Cloudflare Turnstile against bots.') }}</li>
            </ul>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Pages and links') }}</h2>
            <ul class="list-disc space-y-1 ps-5">
                <li>{{ __('A Content Security Policy on every app, limiting where scripts, styles and frames may come from.') }}</li>
                <li>{{ __('Links are checked against known phishing and malware lists when they are added; short links are scanned again every hour.') }}</li>
                <li>{{ __('Anyone can report a link, a page or an image.') }}</li>
            </ul>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Visitors') }}</h2>
            <ul class="list-disc space-y-1 ps-5">
                <li>{{ __('No ad networks and no third-party analytics on HOP\'s own pages.') }}</li>
                <li>{{ __('Visitor counts use a keyed hash of the IP address, never the address itself.') }}</li>
                <li>{{ __('NoteHop notes are end-to-end encrypted in the browser.') }}</li>
            </ul>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Found a problem?') }}</h2>
            <p>{!! __('Please report security problems privately, not in a public issue: open a <a class="underline" href="https://github.com/navi-crwn/HEL.ink/security/advisories/new" rel="noopener">security advisory</a> on the app\'s repository. Each repository has a <code>SECURITY.md</code> with the details. We answer and fix as fast as one person can, and credit reporters who want it.') !!}</p>
        </div>
    </section>
</x-marketing-layout>
