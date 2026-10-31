@php
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="__('Help Center') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 space-y-12">
        <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50/80 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                {{ __('Guides & Documentation') }}
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('HOP Help Center') }}</h1>
            <p class="text-lg {{ $muted }}">
                {{ __('Practical guides and instructions for using HOP ID and every application in the HOP family.') }}
            </p>
        </div>

        {{-- Guide 1: Getting Started with HOP ID --}}
        <div class="{{ $card }} space-y-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white font-bold text-sm shadow-md">ID</span>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Getting Started: Your HOP ID') }}</h2>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 font-mono">hub.hel.ink</p>
                </div>
            </div>
            <ol class="space-y-3 ps-5 list-decimal text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                <li><strong>{{ __('Account Registration:') }}</strong> {{ __('Sign up once at hub.hel.ink/register using your email address or Google Sign-In. You only need one account for the entire family.') }}</li>
                <li><strong>{{ __('Security & Two-Factor Authentication (2FA):') }}</strong> {{ __('Enable two-factor authentication in your HOP ID Settings under Security. Scan the QR code with an authenticator app (e.g. Aegis, 1Password, Google Authenticator) and save your emergency recovery codes in a safe place.') }}</li>
                <li><strong>{{ __('Opening Member Apps:') }}</strong> {{ __('From the central Launcher at hub.hel.ink/dashboard, click on any member app to sign in seamlessly without re-entering your credentials.') }}</li>
                <li><strong>{{ __('Data Backup & Deletion:') }}</strong> {{ __('Under Settings → Backup & Export, you can export your account data as JSON. If you wish to leave, Account Deletion purges all your data across all connected services immediately.') }}</li>
            </ol>
        </div>

        {{-- Guides Grid for Apps --}}
        <div class="grid gap-6 sm:grid-cols-2">
            {{-- LinkHop Guide --}}
            <div class="{{ $card }} space-y-3">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-indigo-500"></span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">LinkHop User Guide</h2>
                </div>
                <ul class="space-y-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    <li>&bull; <strong>{{ __('Shortening Links:') }}</strong> {{ __('Enter a destination URL, pick an optional custom slug, and click Shorten. Your short link is immediately live at hel.ink/l/{slug}.') }}</li>
                    <li>&bull; <strong>{{ __('QR Codes:') }}</strong> {{ __('Every short link automatically generates high-resolution QR codes downloadable in SVG, PNG, and WebP format.') }}</li>
                    <li>&bull; <strong>{{ __('Password Protection:') }}</strong> {{ __('Add a passcode to any link to require visitors to enter the passphrase before being redirected.') }}</li>
                    <li>&bull; <strong>{{ __('Reporting Abuse:') }}</strong> {{ __('If you encounter a suspicious or malicious short link, click the "Report abuse" button on the error page or visit link.hel.ink/report-abuse.') }}</li>
                </ul>
                <div class="pt-2 border-t border-slate-200/60 dark:border-white/10">
                    <a href="https://link.hel.ink/help" class="text-xs font-semibold text-indigo-600 underline hover:text-indigo-700 dark:text-indigo-400">{{ __('Open LinkHop Help') }} &rarr;</a>
                </div>
            </div>

            {{-- BioHop Guide --}}
            <div class="{{ $card }} space-y-3">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-purple-500"></span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">BioHop User Guide</h2>
                </div>
                <ul class="space-y-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    <li>&bull; <strong>{{ __('Claiming Your Page:') }}</strong> {{ __('Choose your username at hel.ink/b/{yourname}. You can edit your bio page at bio.hel.ink.') }}</li>
                    <li>&bull; <strong>{{ __('Adding Blocks:') }}</strong> {{ __('Add link cards, social media links, embedded audio/video players, map locations, and contact cards in any order with drag-and-drop reordering.') }}</li>
                    <li>&bull; <strong>{{ __('Selecting Themes:') }}</strong> {{ __('Choose from over 20 pre-built responsive themes in the editor, or customize backgrounds, button borders, and typography.') }}</li>
                    <li>&bull; <strong>{{ __('Gated Content:') }}</strong> {{ __('Enable an 18+ content disclaimer or a passcode protection gate in page settings for private or mature portfolios.') }}</li>
                </ul>
                <div class="pt-2 border-t border-slate-200/60 dark:border-white/10">
                    <a href="https://bio.hel.ink/help" class="text-xs font-semibold text-purple-600 underline hover:text-purple-700 dark:text-purple-400">{{ __('Open BioHop Help') }} &rarr;</a>
                </div>
            </div>

            {{-- PixelHop Guide --}}
            <div class="{{ $card }} space-y-3">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-cyan-500"></span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">PixelHop User Guide</h2>
                </div>
                <ul class="space-y-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    <li>&bull; <strong>{{ __('Uploading Files:') }}</strong> {{ __('Drag and drop images, short video clips, or PDF documents into pixel.hel.ink to receive instant shareable links at p.hel.ink/{slug}.') }}</li>
                    <li>&bull; <strong>{{ __('Metadata Sanitization:') }}</strong> {{ __('PixelHop automatically strips GPS location coordinates and camera details from images to protect your privacy.') }}</li>
                    <li>&bull; <strong>{{ __('In-Browser Studio:') }}</strong> {{ __('Crop, resize, compress, and apply filters to uploaded images directly in your browser without desktop software.') }}</li>
                    <li>&bull; <strong>{{ __('Desktop ShareX:') }}</strong> {{ __('Download your custom .sxcu config file in PixelHop settings to enable one-click screenshot uploads directly from your desktop.') }}</li>
                </ul>
                <div class="pt-2 border-t border-slate-200/60 dark:border-white/10">
                    <a href="https://pixel.hel.ink/help" class="text-xs font-semibold text-cyan-600 underline hover:text-cyan-700 dark:text-cyan-400">{{ __('Open PixelHop Help') }} &rarr;</a>
                </div>
            </div>

            {{-- NoteHop Guide --}}
            <div class="{{ $card }} space-y-3">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">NoteHop User Guide</h2>
                </div>
                <ul class="space-y-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    <li>&bull; <strong>{{ __('Writing Encrypted Notes:') }}</strong> {{ __('Type your note at note.hel.ink. Your browser encrypts the content with AES-GCM before it leaves your machine.') }}</li>
                    <li>&bull; <strong>{{ __('Sharing Secret Links:') }}</strong> {{ __('Copy the generated link (n.hel.ink/{slug}#k=...). The decryption key is embedded in the hash fragment and is never sent to the server.') }}</li>
                    <li>&bull; <strong>{{ __('Self-Destruct Timers:') }}</strong> {{ __('Select "Burn after reading" for one-time viewing, or set an automatic expiration countdown (1 hour to 30 days).') }}</li>
                    <li>&bull; <strong>{{ __('Key Responsibility:') }}</strong> {{ __('Because the server cannot decrypt your note, lost keys cannot be recovered or reset by anyone.') }}</li>
                </ul>
                <div class="pt-2 border-t border-slate-200/60 dark:border-white/10">
                    <a href="https://note.hel.ink/faq" class="text-xs font-semibold text-emerald-600 underline hover:text-emerald-700 dark:text-emerald-400">{{ __('Open NoteHop Help') }} &rarr;</a>
                </div>
            </div>
        </div>

        {{-- Troubleshooting & Support --}}
        <div class="{{ $card }} space-y-3">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Troubleshooting & Support') }}</h2>
            <p class="text-xs leading-relaxed {{ $muted }}">
                {{ __('If an application appears down or unresponsive, consult our live status monitor at hel.ink/status. For technical bugs or feature suggestions, please open an issue on our GitHub repository.') }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('status') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white dark:bg-white dark:text-slate-900">{{ __('Check System Status') }}</a>
                <a href="https://github.com/navi-crwn/HEL.ink/issues" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 dark:border-white/10 dark:text-white/80">{{ __('GitHub Issues') }}</a>
                <a href="{{ route('contact') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 dark:border-white/10 dark:text-white/80">{{ __('Contact Us') }}</a>
            </div>
        </div>
    </section>
</x-marketing-layout>
