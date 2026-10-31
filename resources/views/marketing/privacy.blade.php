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
                <li>&check; {{ __('No third-party advertising networks or tracking cookies.') }}</li>
                <li>&check; {{ __('No data brokers, selling, or sharing of personal data.') }}</li>
                <li>&check; {{ __('Automatic cryptographic hashing of IP addresses in analytics.') }}</li>
                <li>&check; {{ __('Automatic data purging on strict 30-day and 90-day retention schedules.') }}</li>
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
                        {{ __('We store short-link slugs, destination URLs, and optional link protection passwords. Click analytics record total clicks, referrer domains, device types, and country totals. Visitor IP addresses are hashed with a daily rotated salt and are never visible to link creators or stored permanently.') }}
                    </p>
                </div>

                {{-- BioHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">BioHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('We store bio page layout blocks, links, text, and user-uploaded media (avatars, banners). If you choose to configure a tracking pixel (Google Analytics, Meta, TikTok), it executes solely upon explicit visitor cookie consent. HOP does not receive or process that external pixel data.') }}
                    </p>
                </div>

                {{-- PixelHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">PixelHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('Uploaded images, documents, and video files are stored securely. Before public serving, PixelHop automatically strips sensitive EXIF metadata—including GPS geographic coordinates, camera serial numbers, and device information—to safeguard uploader anonymity.') }}
                    </p>
                </div>

                {{-- NoteHop --}}
                <div class="{{ $card }}">
                    <h3 class="font-bold text-slate-900 dark:text-white">NoteHop</h3>
                    <p class="mt-2 text-xs leading-relaxed {{ $muted }}">
                        {{ __('Zero-Knowledge Storage: NoteHop encrypts all note text and attachments client-side in your browser using AES-GCM before sending anything over the network. The server only receives encrypted ciphertext; decryption keys reside exclusively in the URL fragment (#k=...) and are never sent to the server.') }}
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
                    {{ __('You may delete your account at any time. Account deletion triggers an immediate cascade that wipes your profile, credentials, short links, bio pages, uploaded media, and notes across every HOP service with zero recovery delay.') }}
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
