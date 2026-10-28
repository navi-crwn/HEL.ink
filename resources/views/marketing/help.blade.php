<x-marketing-layout title="Help - HOP" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Help center</h1>
        <div class="mt-6 space-y-6 text-slate-700 dark:text-white/80">
            <div><h2 class="font-semibold text-slate-900 dark:text-white">Your HOP ID</h2><p class="mt-1 text-sm">Create it once at <a class="underline" href="{{ hub_url('register') }}">hub.hel.ink</a>. Sign in with a password or Google, add two-factor authentication in Settings, and open any app from the launcher. Each app's "Sign in" button brings you back here.</p></div>
            <div><h2 class="font-semibold text-slate-900 dark:text-white">Short links</h2><p class="mt-1 text-sm">Links live at <code>hel.ink/l/your-slug</code> and are managed in <a class="underline" href="https://link.hel.ink">LinkHop</a>. The old form without <code>/l/</code> keeps redirecting for a while after the move; see announcements.</p></div>
            <div><h2 class="font-semibold text-slate-900 dark:text-white">Bio pages</h2><p class="mt-1 text-sm">Your page is <code>hel.ink/b/your-slug</code>, edited in <a class="underline" href="https://bio.hel.ink">BioHop</a>. Link and bio slugs are separate namespaces.</p></div>
            <div><h2 class="font-semibold text-slate-900 dark:text-white">Something is down?</h2><p class="mt-1 text-sm">Check the <a class="underline" href="{{ route('status') }}">status page</a>. A watchdog probes every app each minute and the owner is alerted automatically.</p></div>
            <div><h2 class="font-semibold text-slate-900 dark:text-white">Delete your account or export your data</h2><p class="mt-1 text-sm">Settings at the hub: export your account data as JSON, follow the links to each app's export, then delete the account. Deleting removes your content in every app.</p></div>
            <div><h2 class="font-semibold text-slate-900 dark:text-white">Still stuck?</h2><p class="mt-1 text-sm">See the <a class="underline" href="{{ route('faq') }}">FAQ</a> or <a class="underline" href="{{ route('contact') }}">get in touch</a>.</p></div>
        </div>
    </section>
</x-marketing-layout>
