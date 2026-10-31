@php
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="__('Contact') . ' · HOP'" :themed="true">
    {{-- Sprite definitions --}}
    <svg xmlns="http://www.w3.org/2000/svg" class="hidden" aria-hidden="true">
        <symbol id="i-github" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></symbol>
        <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></symbol>
        <symbol id="i-shield-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="M12 8v4"/><path d="M12 16h.01"/></symbol>
        <symbol id="i-flag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></symbol>
    </svg>

    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
        <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50/80 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                {{ __('Support & Inquiries') }}
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('Contact HOP') }}</h1>
            <p class="text-lg {{ $muted }}">
                {{ __('Have a question, feedback, or a security issue? Here are the best ways to reach out.') }}
            </p>
        </div>

        <div class="mt-10 grid gap-6 sm:grid-cols-2">
            {{-- Primary Channel: GitHub Issues --}}
            <div class="{{ $card }} sm:col-span-2 border-indigo-300/60 bg-indigo-50/40 dark:border-indigo-500/20 dark:bg-indigo-950/20">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-md">
                        <svg class="h-5 w-5"><use href="#i-github"/></svg>
                    </span>
                    <div>
                        <span class="rounded bg-indigo-600/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">{{ __('Recommended Channel') }}</span>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('GitHub Issues & Discussions') }}</h2>
                    </div>
                </div>
                <p class="mt-3 text-sm leading-relaxed {{ $muted }}">
                    {{ __('For bug reports, feature requests, and open questions, GitHub is our primary and fastest channel. Public tracking allows the community to verify fixes and follow development.') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="https://github.com/navi-crwn/HEL.ink/issues" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-white/90">
                        {{ __('Open an issue on GitHub') }} &rarr;
                    </a>
                </div>
            </div>

            {{-- General Support Email --}}
            <div class="{{ $card }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                        <svg class="h-5 w-5"><use href="#i-mail"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('General Support') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Accounts & Help') }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-relaxed {{ $muted }}">
                    {{ __('Questions regarding your HOP ID, sign-in problems, or general ecosystem inquiries.') }}
                </p>
                <div class="mt-4 pt-2 border-t border-slate-200/60 dark:border-white/10">
                    <!--email_off-->
                    <a href="mailto:support@hel.ink" class="text-xs font-semibold font-mono text-indigo-600 underline underline-offset-2 hover:text-indigo-700 dark:text-indigo-400">support@hel.ink</a>
                    <!--/email_off-->
                </div>
            </div>

            {{-- Security & Vulnerabilities --}}
            <div class="{{ $card }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                        <svg class="h-5 w-5"><use href="#i-shield-alert"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Security Advisories') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Vulnerability Reports') }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-relaxed {{ $muted }}">
                    {{ __('Responsible disclosures of potential vulnerabilities, cryptographic weaknesses, or authentication bugs.') }}
                </p>
                <div class="mt-4 flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-white/10">
                    <!--email_off-->
                    <a href="mailto:security@hel.ink" class="text-xs font-semibold font-mono text-rose-600 underline underline-offset-2 hover:text-rose-700 dark:text-rose-400">security@hel.ink</a>
                    <!--/email_off-->
                    <a href="https://github.com/navi-crwn/HEL.ink/security/advisories/new" target="_blank" rel="noopener noreferrer" class="text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                        {{ __('Advisory form') }} &rarr;
                    </a>
                </div>
            </div>

            {{-- Abuse & Malware Reports --}}
            <div class="{{ $card }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <svg class="h-5 w-5"><use href="#i-flag"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Report Abuse') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Phishing & Malware') }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-relaxed {{ $muted }}">
                    {{ __('Report malicious links, phishing attempts, impersonation bio pages, or copyright infringement.') }}
                </p>
                <div class="mt-4 pt-2 border-t border-slate-200/60 dark:border-white/10 flex items-center justify-between">
                    <a href="https://link.hel.ink/report-abuse" class="text-xs font-semibold text-amber-600 underline underline-offset-2 hover:text-amber-700 dark:text-amber-400">
                        {{ __('Abuse report form') }} &rarr;
                    </a>
                    <!--email_off-->
                    <a href="mailto:security@hel.ink" class="text-xs font-mono text-slate-500 hover:text-slate-700 dark:text-slate-400">security@hel.ink</a>
                    <!--/email_off-->
                </div>
            </div>

            {{-- Feedback & Ideas --}}
            <div class="{{ $card }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <svg class="h-5 w-5"><use href="#i-mail"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Feedback & Ideas') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Thoughts & Ideas') }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-relaxed {{ $muted }}">
                    {{ __('Have suggestions or thoughts on how to make HOP apps better? We would love to hear from you.') }}
                </p>
                <div class="mt-4 pt-2 border-t border-slate-200/60 dark:border-white/10">
                    <!--email_off-->
                    <a href="mailto:feedback@hel.ink" class="text-xs font-semibold font-mono text-emerald-600 underline underline-offset-2 hover:text-emerald-700 dark:text-emerald-400">feedback@hel.ink</a>
                    <!--/email_off-->
                </div>
            </div>
        </div>

        <div class="mt-12 rounded-2xl border border-slate-200/80 bg-slate-100/50 p-4 text-xs text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
            <p>{{ __('Response Times: HOP is independently developed and maintained. Most inquiries are reviewed within 48 to 72 hours. Urgent security advisories receive priority handling.') }}</p>
        </div>
    </section>
</x-marketing-layout>
