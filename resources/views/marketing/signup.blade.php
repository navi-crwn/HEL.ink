<x-marketing-layout :title="__('How to sign up').' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ __('How to sign up') }}</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p>{{ __('One free HOP ID opens every app. It takes a minute.') }}</p>
            <ol class="list-decimal space-y-2 ps-5">
                <li>{!! __('Open <a class="underline" href=":url">hub.hel.ink/register</a>.', ['url' => hub_url('register')]) !!}</li>
                <li>{{ __('Sign up with your email and a password, or continue with Google.') }}</li>
                <li>{{ __('Confirm your email address with the link we send.') }}</li>
                <li>{!! __('Recommended: turn on two-factor codes in <strong>Settings → Security</strong>.') !!}</li>
                <li>{{ __('Open any app from the launcher. You are already signed in.') }}</li>
            </ol>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Signed up with Google?') }}</h2>
            <p>{{ __('You can add a password later in your settings, so you can still sign in if you lose access to Google.') }}</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Leaving') }}</h2>
            <p>{{ __('Download your data or delete your account at any time in your settings. Deleting the account deletes your links, bio pages, images and notes in every app.') }}</p>
            <p><a href="{{ hub_url('register') }}" class="mt-2 inline-block rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white no-underline dark:bg-white dark:text-slate-900">{{ __('Create your HOP ID') }}</a></p>
        </div>
    </section>
</x-marketing-layout>
