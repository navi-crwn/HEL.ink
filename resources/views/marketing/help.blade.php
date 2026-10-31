<x-marketing-layout :title="__('Help') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ __('Help center') }}</h1>
        <div class="mt-6 space-y-6 text-slate-700 dark:text-white/80">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Your HOP ID') }}</h2>
                <p class="mt-1 text-sm">{!! __('Create it once at <a class="underline" href=":url">hub.hel.ink</a>. Sign in with a password or Google, add two-factor authentication in Settings, and open any app from the launcher. Each app\'s "Sign in" button brings you back here.', ['url' => hub_url('register')]) !!}</p>
            </div>
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Short links') }}</h2>
                <p class="mt-1 text-sm">{!! __('Links live at <code>hel.ink/l/your-slug</code> and are managed in <a class="underline" href="https://link.hel.ink">LinkHop</a>. The old form without <code>/l/</code> keeps redirecting for a while after the move; see announcements.') !!}</p>
            </div>
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Bio pages') }}</h2>
                <p class="mt-1 text-sm">{!! __('Your page is <code>hel.ink/b/your-slug</code>, edited in <a class="underline" href="https://bio.hel.ink">BioHop</a>. Link and bio slugs are separate namespaces.') !!}</p>
            </div>
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Something is down?') }}</h2>
                <p class="mt-1 text-sm">{!! __('Check the <a class="underline" href=":url">status page</a>. A watchdog probes every app each minute and the owner is alerted automatically.', ['url' => route('status')]) !!}</p>
            </div>
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Delete your account or export your data') }}</h2>
                <p class="mt-1 text-sm">{{ __('Settings at the hub: export your account data as JSON, follow the links to each app\'s export, then delete the account. Deleting removes your content in every app.') }}</p>
            </div>
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Still stuck?') }}</h2>
                <p class="mt-1 text-sm">{!! __('See the <a class="underline" href=":faq">FAQ</a> or <a class="underline" href=":contact">get in touch</a>.', ['faq' => route('faq'), 'contact' => route('contact')]) !!}</p>
            </div>
        </div>
    </section>
</x-marketing-layout>
