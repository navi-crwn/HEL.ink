<x-marketing-layout :title="__('Privacy Policy') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 space-y-6 text-slate-700 dark:text-white/80 text-sm leading-relaxed">
        <h1 class="text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ __('Privacy policy') }}</h1>
        <p>{!! __('<strong>Short version:</strong> HOP stores only the data required to keep your links online and protect the platform from abuse.') !!}</p>
        <div class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 space-y-4 dark:border-white/10 dark:bg-white/3">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Data we collect') }}</h2>
            <ul class="list-disc space-y-2 ps-6">
                <li>{{ __('Account information: name, email, hashed password.') }}</li>
                <li>{{ __('Link metadata: slug, destination URL, folders/tags, optional password hash.') }}</li>
                <li>{{ __('Click analytics: timestamp, IP (stored only as a keyed hash, never shown to link owners), approximate location (country, region, city) and network provider via IP geolocation, user agent, referrer. Link owners see location and network only as totals, never per visit. Clicks on Link-in-Bio pages are logged the same way (country, device, browser, referrer, hashed IP).') }}</li>
                <li>{{ __('Sign-in history: for your own account we keep your last 5 sign-ins (IP address, approximate location, network, device and browser) so you and our team can spot logins you don\'t recognise.') }}</li>
                <li>{{ __('Activity log: what you created, changed or deleted in your account, with the IP address and browser used.') }}</li>
                <li>{{ __('Abuse reports: the reported link, your reason and the email you give us, plus your IP address for a short time to stop report spam.') }}</li>
                <li>{{ __('Bio page uploads: avatars, backgrounds, images and icons you upload for your bio pages.') }}</li>
                <li>{{ __('Bio page trackers: a bio page owner can add Google Analytics, a Facebook pixel or a TikTok pixel to their page. These run only after a visitor clicks "Allow" on that page, and then send data to those companies under their own privacy policies. HOP does not receive that data. The choice is stored in the visitor\'s browser for that page.') }}</li>
            </ul>
        </div>
        <div class="rounded-3xl border border-slate-200/80 bg-white/70 p-6 space-y-3 dark:border-white/10 dark:bg-white/3">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Retention') }}</h2>
            <ul class="list-disc space-y-2 ps-6">
                <li>{{ __('Click logs (short links and bio pages) are automatically purged after 30 days.') }}</li>
                <li>{{ __('Sign-in history and the activity log are deleted after 90 days.') }}</li>
                <li>{{ __('The IP address on an abuse report is removed after 30 days; closed reports are deleted after 180 days.') }}</li>
                <li>{{ __('Delete a link to remove its remaining metadata immediately. Deleting your account removes your links, bio pages, uploaded files, sign-in history and activity log.') }}</li>
            </ul>
            <p>{{ __('You can download everything stored about your account from Settings → Backup & Export → Download My Account Data.') }}</p>
        </div>
        <p>{!! __('Questions about privacy can be sent to <a class="underline" href="mailto:privacy@hel.ink">privacy@hel.ink</a>.') !!}</p>
    </section>
</x-marketing-layout>
