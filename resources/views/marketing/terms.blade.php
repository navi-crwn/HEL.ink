<x-marketing-layout :title="__('Terms of Service') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 space-y-6 text-slate-700 dark:text-white/80 text-sm leading-relaxed">
        <h1 class="text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ __('Terms of service') }}</h1>
        <p>{{ __('By using HOP you agree to the following principles:') }}</p>
        <ol class="list-decimal space-y-3 ps-6">
            <li>{{ __('Do not shorten content that is illegal, abusive, or violates someone else’s rights.') }}</li>
            <li>{{ __('Respect rate limits and quotas. Automation or spam traffic is not allowed.') }}</li>
            <li>{{ __('Admins may remove links or accounts that harm the community or infrastructure.') }}</li>
        </ol>
        <p>{{ __('These terms may change as HOP evolves. We will announce updates in the dashboard and changelog.') }}</p>
        <p>{!! __('Contact <a class="underline" href="mailto:legal@hel.ink">legal@hel.ink</a> for clarification.') !!}</p>
        <section class="mt-8 space-y-2">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('HOP family') }}</h2>
            <p>{{ __('Your HOP ID is one account for every HOP app (LinkHop, BioHop, PixelHop and any app added later). These terms apply to all of them; suspension or deletion of the account applies to all of them.') }}</p>
            <p>{{ __('The service is free of charge. Features, addresses and limits can change; when they do, we announce it on the hub (hub.hel.ink) and at hel.ink/announcements before the change takes effect, and by email when it affects your existing content. Continued use after a change means you accept it. Changes that are required for security or to keep the service running may be applied at once.') }}</p>
        </section>
    </section>
</x-marketing-layout>
