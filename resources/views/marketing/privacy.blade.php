@php
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="__('Master Privacy Policy') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 space-y-8 text-slate-700 dark:text-white/80 text-sm leading-relaxed">
        <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50/80 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                {{ __('Privacy & Data Protection') }}
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('HOP Master Privacy Policy') }}</h1>
            <p class="text-base {{ $muted }}">
                {{ __('We believe privacy is a fundamental human right. HOP services are designed from the ground up to minimize data collection, prevent surveillance, and never monetize user information.') }}
            </p>
        </div>

        {{-- Core Privacy Commitments --}}
        <div class="{{ $card }} border-emerald-300/60 bg-emerald-50/30 dark:border-emerald-500/20 dark:bg-emerald-950/20">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Our Privacy Commitments') }}</h2>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2 text-xs text-slate-700 dark:text-slate-300">
                <li>&check; {{ __('HOP uses no advertising networks and no tracking cookies of its own.') }}</li>
                <li>&check; {{ __('HOP does not sell your data, does not use data brokers, and does not share your personal data with advertisers. The one exception is a tracking pixel that a bio page owner chooses to add (see BioHop below).') }}</li>
                <li>&check; {{ __('Automatic cryptographic hashing of IP addresses in analytics.') }}</li>
                <li>&check; {{ __('Automatic data purging on strict 30-day and 90-day retention schedules.') }}</li>
                <li>&check; {{ __('HOP checks for malware, phishing and illegal material, for safety only: destination links in LinkHop, uploaded files in PixelHop, and content that someone reports, such as bio pages and basic notes. Confirmed harmful or illegal content is blocked. Encrypted notes cannot be inspected, because the server cannot read them.') }}</li>
            </ul>
        </div>

        {{-- Section 1: Account Data (HOP ID) --}}
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('1. Account & Authentication Data') }}</h2>
            <div class="{{ $card }} space-y-3">
                <p class="text-xs leading-relaxed {{ $muted }}">
                    {{ __('When you create a HOP ID at hub.hel.ink, we store your email address, username, and a cryptographically hashed password (Argon2id/Bcrypt). If you sign in via Google OAuth, we receive and verify your email address and profile identity from Google.') }}
                </p>
                <p class="text-xs leading-relaxed {{ $muted }}">
                    {{ __('For your account security, we maintain a rolling log of your last 5 sign-ins (timestamp, IP address, device type, and approximate country/city) so you can audit access and detect unauthorized logins. This sign-in history is automatically purged after 90 days.') }}
                </p>
            </div>
        </div>

        {{-- Section 2: Application Data Collection --}}
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('2. Application-Specific Data Handling') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                {{-- LinkHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">LinkHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('We store short-link slugs, destination URLs, and optional link protection passwords. Click analytics record the time, country and city, network provider, device and browser type, and the referring site. We keep a keyed hash of each visitor\'s IP address, never the address itself, plus a daily visitor hash that changes every day at 00:00 UTC, so a visitor cannot be followed from one day to the next. Link creators never see visitor IP addresses.') }}
                    </p>
                </div>

                {{-- BioHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">BioHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('We store bio page layout blocks, links, text, and user-uploaded media (avatars, banners). A bio page owner can add a tracking pixel (Google Analytics, Meta or TikTok). The page owner chooses to add it and is responsible for it; HOP only provides the platform. The pixel loads only after the visitor agrees. When it loads, the visitor\'s browser sends data to that service under its own privacy policy. HOP does not receive or process that data.') }}
                    </p>
                </div>

                {{-- PixelHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">PixelHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('PixelHop stores the files you upload. When you upload them, photos (JPEG, PNG and WebP) and videos lose their embedded metadata, including GPS location, camera make, model and serial number, and device details. PDFs are compressed, but their document properties are kept, so remove personal details from a PDF before you upload it.') }}
                    </p>
                </div>

                {{-- NoteHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">NoteHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('Encrypted notes (the default) and their attachments are encrypted in your browser with AES-GCM before anything is sent over the network. The server only receives ciphertext; the key stays in the URL fragment (#k=...) and is never sent to the server. A basic note is not encrypted this way: the server can read it. An encrypted note can be read only by people who have the full link, including the part after #. Share the link only with the people you want to read it.') }}
                    </p>
                </div>

                {{-- FileHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">FileHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('FileHop converts files inside your browser tab. Your files, their names and the results never reach the server, so there is nothing of yours to store, scan or share. If you sign in with HOP ID, FileHop keeps your HOP ID number, name and email and a session cookie; signing out, or being signed out by the hub, deletes the session.') }}
                    </p>
                </div>

                {{-- PayHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">PayHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('PayHop builds payment QR codes inside your browser tab. The names, account numbers and amounts you type are not sent to the server. If you sign in with HOP ID, PayHop keeps your HOP ID number, name and email and a session cookie. PayHop stores a payment method or a bill only if you choose to save it: each saved method is encrypted and kept only after you agree to that method, is never written to logs and never shown in the admin panel, and you can delete one method or everything at any time. History keeps your 200 most recent bills.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Section 3: Data Retention & Purging --}}
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('3. Data Retention Schedules') }}</h2>
            <div class="{{ $card }} space-y-2 text-xs text-slate-600 dark:text-slate-400">
                <p>&bull; <strong>{{ __('Click logs (LinkHop & BioHop):') }}</strong> {{ __('Detailed click logs are permanently purged after 30 days.') }}</p>
                <p>&bull; <strong>{{ __('Authentication & Activity logs:') }}</strong> {{ __('Sign-in history and account activity records are deleted after 90 days.') }}</p>
                <p>&bull; <strong>{{ __('Abuse reports:') }}</strong> {{ __('IP addresses attached to abuse reports are purged after 30 days; closed report records after 180 days.') }}</p>
                <p>&bull; <strong>{{ __('FileHop:') }}</strong> {{ __('Files are never stored. A FileHop session is deleted when you sign out or when the hub signs you out.') }}</p>
                <p>&bull; <strong>{{ __('PayHop:') }}</strong> {{ __('Nothing is stored unless you save it. A saved payment method stays until you delete it, history keeps the 200 most recent bills, and a PayHop session is deleted when you sign out or when the hub signs you out.') }}</p>
                <p>&bull; <strong>{{ __('Expired & Ephemeral Notes:') }}</strong> {{ __('Notes configured with self-destruct timers or read-burn limits are permanently deleted immediately upon expiration.') }}</p>
            </div>
        </div>

        {{-- Section 4: Your Rights --}}
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('4. Your Rights: Export & Deletion') }}</h2>
            <div class="{{ $card }} space-y-3 text-xs leading-relaxed {{ $muted }}">
                <p>
                    {{ __('You hold full control over your data. In your HOP ID Settings under "Backup & Export", you can download a complete JSON archive of your account records across all member apps.') }}
                </p>
                <p>
                    {{ __('You may delete your account at any time. Deleting it immediately removes your profile, credentials, short links, bio pages, uploaded media and notes from every HOP service. Backup copies are not edited: copies kept on our own server are deleted automatically after 14 days.') }}
                </p>
            </div>
        </div>

        {{-- Contact --}}
        <div class="border-t border-slate-200/80 pt-6 dark:border-white/10 space-y-2">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('5. Privacy Inquiries') }}</h2>
            <p class="text-xs {{ $muted }}">
                {{ __('For privacy questions, data requests, or compliance inquiries:') }}
            </p>
            <div class="flex flex-wrap items-center gap-4 text-xs font-mono">
                <!--email_off-->
                <a href="mailto:privacy@hel.ink" class="text-indigo-600 underline underline-offset-2 hover:text-indigo-700 dark:text-indigo-400">privacy@hel.ink</a>
                <a href="mailto:support@hel.ink" class="text-indigo-600 underline underline-offset-2 hover:text-indigo-700 dark:text-indigo-400">support@hel.ink</a>
                <!--/email_off-->
                <a href="https://github.com/navi-crwn/HEL.ink/issues" target="_blank" rel="noopener noreferrer" class="font-sans text-slate-600 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('GitHub Issues') }} &rarr;</a>
            </div>
        </div>
    </section>
</x-marketing-layout>
