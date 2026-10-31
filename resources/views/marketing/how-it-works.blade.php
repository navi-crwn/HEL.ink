<x-marketing-layout :title="__('How it works').' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ __('How it works') }}</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p>HOP is a family of separate apps that feel like one: one account, one sign-in, one sign-out.</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">One account: HOP ID</h2>
            <p>Your account lives at the hub, <strong>hub.hel.ink</strong>: email and password or Google, two-factor codes, your profile, API tokens, your data download and account deletion. The apps keep no passwords.</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">Signing in to an app</h2>
            <ol class="list-decimal space-y-1 ps-5">
                <li>You press Sign in on an app, for example LinkHop.</li>
                <li>The app sends you to the hub. If you are already signed in there, you do not type anything.</li>
                <li>The hub sends you back with a <strong>ticket</strong>: signed with that app's secret, valid for a few seconds, usable once, and only for that app.</li>
                <li>The app checks the ticket and starts its own session.</li>
            </ol>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">Signing out everywhere</h2>
            <p>Signing out at the hub hops through each app's sign-out and ends on the home page, so no app keeps you signed in by accident.</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">Apps talking to each other</h2>
            <p>When one app asks another for something (for example, BioHop asking LinkHop for a short link, or the hub telling every app that you deleted your account), the request is signed with a shared secret, carries a timestamp and a one-time nonce, and is refused if it is old or replayed.</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">NoteHop is different on purpose</h2>
            <p>NoteHop encrypts a note in your browser before saving it. The key is in the part of the link after <code>#</code>, which browsers never send to a server. HOP ID only decides who can manage a note, never who can read it.</p>
            <p>More: <a class="underline" href="{{ route('security') }}">{{ __('Security') }}</a> · <a class="underline" href="{{ route('signup') }}">{{ __('How to sign up') }}</a></p>
        </div>
    </section>
</x-marketing-layout>
