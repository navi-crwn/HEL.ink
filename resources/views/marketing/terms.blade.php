@php
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="__('Master Terms of Service') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 space-y-8 text-slate-700 dark:text-white/80 text-sm leading-relaxed">
        <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50/80 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                {{ __('Legal Agreement') }}
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('HOP Master Terms of Service') }}</h1>
            <p class="text-base {{ $muted }}">
                {{ __('These Master Terms of Service govern your use of the HOP family of applications. By creating a HOP ID or using any HOP service, you agree to these Master Terms and the respective application policies.') }}
            </p>
        </div>

        {{-- Overview Card --}}
        <div class="{{ $card }}">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('The HOP Family & Single Account') }}</h2>
            <p class="mt-2 text-sm leading-relaxed {{ $muted }}">
                {{ __('Your HOP ID is a unified account providing authenticated access to all member apps: LinkHop, BioHop, PixelHop, and NoteHop. These Master Terms form a single binding agreement across the entire ecosystem. Suspension or deletion of your HOP ID applies universally across every connected service.') }}
            </p>
        </div>

        {{-- Section 1: Common Ecosystem Terms --}}
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('1. General Ecosystem Rules') }}</h2>
            <div class="{{ $card }} space-y-4">
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ __('1.1 HOP ID and Authentication') }}</h3>
                    <p class="mt-1 text-xs leading-relaxed {{ $muted }}">
                        {{ __('You are responsible for maintaining the confidentiality of your account credentials, including passwords, passkeys, and two-factor authentication recovery codes. You must not share credentials or permit unauthorized third parties to access your account.') }}
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ __('1.2 Chained Logout and Session Security') }}</h3>
                    <p class="mt-1 text-xs leading-relaxed {{ $muted }}">
                        {{ __('Logging out from any HOP member app or from the central hub initiates a chained logout signal across all member applications where you currently hold an active session, ensuring comprehensive session termination.') }}
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ __('1.3 Acceptable Use and Platform Protection') }}</h3>
                    <p class="mt-1 text-xs leading-relaxed {{ $muted }}">
                        {{ __('You agree not to use HOP services for illegal purposes, distributing malware, conducting phishing schemes, distributing spam, performing denial-of-service attacks, brute-forcing endpoints, or scraping at rates that degrade service quality for others.') }}
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ __('1.4 Mandatory Content Warning & Classification (18+ & Sensitive Content)') }}</h3>
                    <p class="mt-1 text-xs leading-relaxed {{ $muted }}">
                        {{ __('Content creators across all HOP applications (LinkHop, BioHop, PixelHop, NoteHop) have an affirmative obligation to classify any content that contains mature, 18+ adult, sexually suggestive, violent, or sensitive material using the unified 3-level classification system (None, Sensitive, Adult 18+). Flagged content presents an interstitial warning gate with age verification prior to viewing, redirecting, or decrypting, and is strictly excluded from public explore feeds and search indexing. Failure to classify adult content is a violation of these Master Terms and may result in moderation actions or HOP ID suspension.') }}
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">{{ __('1.5 Data Ownership, Export, and Deletion') }}</h3>
                    <p class="mt-1 text-xs leading-relaxed {{ $muted }}">
                        {{ __('You retain ownership of the content you publish or store. You may export your complete account data as JSON at any time from your settings. Deleting your HOP ID permanently erases your profile and all associated content across every HOP application.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Section 2: Application-Specific Terms --}}
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('2. Application-Specific Terms') }}</h2>
            <p class="text-xs {{ $muted }}">
                {{ __('Each member application maintains specific rules tailored to its functionality. Both these Master Terms and the respective application policies are legally binding.') }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                {{-- LinkHop --}}
                <div class="{{ $card }}">
                    <div class="flex items-center gap-2.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                        <h3 class="font-bold text-slate-900 dark:text-white">LinkHop (link.hel.ink)</h3>
                    </div>
                    <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-400">
                        <li>&bull; {{ __('All target URLs are scanned for reputation and safety. Links redirecting to phishing, malware, or deceptive destinations will be disabled immediately.') }}</li>
                        <li>&bull; {{ __('Automated short-link creation via API must adhere to published rate limits. Spam creation of disposable short links is forbidden.') }}</li>
                        <li>&bull; {{ __('Short links live under hel.ink/l/{slug}. Legacy links created prior to namespace isolation redirect under published transition policies.') }}</li>
                    </ul>
                </div>

                {{-- BioHop --}}
                <div class="{{ $card }}">
                    <div class="flex items-center gap-2.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span>
                        <h3 class="font-bold text-slate-900 dark:text-white">BioHop (bio.hel.ink)</h3>
                    </div>
                    <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-400">
                        <li>&bull; {{ __('You are solely responsible for the links, embeds, text, images, and contact information published on your bio page.') }}</li>
                        <li>&bull; {{ __('Pages containing mature or sensitive material must enable the built-in age/content warning gate. Impersonation of brands or individuals is prohibited.') }}</li>
                        <li>&bull; {{ __('Custom CSS and theme customizations must not conceal safety indicators or spoof authentication prompts.') }}</li>
                    </ul>
                </div>

                {{-- PixelHop --}}
                <div class="{{ $card }}">
                    <div class="flex items-center gap-2.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-cyan-500"></span>
                        <h3 class="font-bold text-slate-900 dark:text-white">PixelHop (pixel.hel.ink)</h3>
                    </div>
                    <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-400">
                        <li>&bull; {{ __('You may host images, short clips, and document files (PDF) within fair upload quotas. Shared files are served at p.hel.ink.') }}</li>
                        <li>&bull; {{ __('For visitor privacy, location (GPS) and personal EXIF metadata are stripped from uploaded imagery before public delivery.') }}</li>
                        <li>&bull; {{ __('You must own the rights to files you upload. Verified DMCA copyright notices with proof of ownership result in immediate file removal.') }}</li>
                    </ul>
                </div>

                {{-- NoteHop --}}
                <div class="{{ $card }}">
                    <div class="flex items-center gap-2.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        <h3 class="font-bold text-slate-900 dark:text-white">NoteHop (note.hel.ink)</h3>
                    </div>
                    <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-400">
                        <li>&bull; {{ __('Zero-knowledge encryption: Note text and files are encrypted in your browser with AES-GCM before transmission. The decryption key never touches our server.') }}</li>
                        <li>&bull; {{ __('Key custody: Because the server has no access to your key, HOP administrators cannot recover, decrypt, or reset lost note links or keys.') }}</li>
                        <li>&bull; {{ __('Self-destructing and expired notes are permanently purged from server memory upon redemption or timer completion.') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Section 3: Disclaimers & Law --}}
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('3. Disclaimers & Limitation of Liability') }}</h2>
            <div class="{{ $card }} space-y-3 text-xs {{ $muted }}">
                <p>
                    {{ __('HOP services and code are provided "as is" and "as available", without warranty of any kind, express or implied. We do not warrant that operation will be error-free or uninterrupted.') }}
                </p>
                <p>
                    {{ __('To the maximum extent permitted by applicable law, in no event shall navi-crwn or infrastructure providers be held liable for any indirect, incidental, or consequential damages resulting from your use or inability to use the services.') }}
                </p>
            </div>
        </div>

        {{-- Contact & Legal Inquiries --}}
        <div class="border-t border-slate-200/80 pt-6 dark:border-white/10 space-y-2">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('4. Questions & Legal Inquiries') }}</h2>
            <p class="text-xs {{ $muted }}">
                {{ __('For legal questions, compliance inquiries, or policy clarification:') }}
            </p>
            <div class="flex flex-wrap items-center gap-4 text-xs font-mono">
                <!--email_off-->
                <a href="mailto:legal@hel.ink" class="text-indigo-600 underline underline-offset-2 hover:text-indigo-700 dark:text-indigo-400">legal@hel.ink</a>
                <a href="mailto:support@hel.ink" class="text-indigo-600 underline underline-offset-2 hover:text-indigo-700 dark:text-indigo-400">support@hel.ink</a>
                <!--/email_off-->
                <a href="https://github.com/navi-crwn/HEL.ink/issues" target="_blank" rel="noopener noreferrer" class="font-sans text-slate-600 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('GitHub Issues') }} &rarr;</a>
            </div>
        </div>
    </section>
</x-marketing-layout>
