@php
    $card = 'rounded-3xl border border-slate-200/80 bg-white/70 p-6 dark:border-white/10 dark:bg-white/3';
    $muted = 'text-slate-600 dark:text-white/70';
@endphp
<x-marketing-layout :title="__('Master FAQ') . ' · HOP'" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 space-y-12">
        <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50/80 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                {{ __('Frequently Asked Questions') }}
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('Frequently Asked Questions') }}</h1>
            <p class="text-lg {{ $muted }}">
                {{ __('Everything you need to know about HOP, your HOP ID, and each app in our family.') }}
            </p>
        </div>

        {{-- Table of Contents Quick Links --}}
        <div class="flex flex-wrap gap-2 text-xs">
            <a href="#general" class="rounded-xl border border-slate-200 bg-white/80 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">{{ __('General & HOP ID') }}</a>
            <a href="#linkhop" class="rounded-xl border border-slate-200 bg-white/80 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">LinkHop</a>
            <a href="#biohop" class="rounded-xl border border-slate-200 bg-white/80 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">BioHop</a>
            <a href="#pixelhop" class="rounded-xl border border-slate-200 bg-white/80 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">PixelHop</a>
            <a href="#notehop" class="rounded-xl border border-slate-200 bg-white/80 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80">NoteHop</a>
        </div>

        {{-- Section 1: General & HOP ID --}}
        <section id="general" class="space-y-4 scroll-mt-24">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-xs">ID</span>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('1. General & HOP ID') }}</h2>
            </div>
            <dl class="space-y-3">
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What is HOP and what apps belong to the family?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('HOP stands for Hop Easy Link. It is a family of four independent, open-source web services: LinkHop (short links & QR codes), BioHop (link-in-bio pages), PixelHop (image and document hosting), and NoteHop (zero-knowledge encrypted notes). They are tied together by one account system: your HOP ID.') }}</p>
                </details>

                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How does Single Sign-On (SSO) work with my HOP ID?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('You sign in once at hub.hel.ink. When you open LinkHop, BioHop, PixelHop, or NoteHop, you are automatically recognized via signed cryptographic session tickets. You never have to create separate accounts or remember multiple passwords.') }}</p>
                </details>

                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What is chained logout and how does it protect me?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('When you log out from any member app or the hub, HOP executes a chained logout sequence that securely terminates your active sessions across all connected applications. This ensures that leaving a shared computer leaves no active login behind.') }}</p>
                </details>

                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Is HOP free? Are there paid tiers, limits, or ads?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Every app is 100% free with no premium paywalls, no subscription tiers, and no advertising. Generous fair-use quotas are applied uniformly to protect server resources from spam and denial-of-service abuse.') }}</p>
                </details>

                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How is visitor privacy protected across all apps?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('We do not use surveillance trackers, third-party analytics, or data brokers. Visitor IP addresses in analytics are cryptographically hashed with rotating salts and permanently purged after 30 days.') }}</p>
                </details>

                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How do I export my data or delete my account?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Visit Settings in hub.hel.ink. Under "Backup & Export", you can download a full JSON dump of your account data. Under "Delete Account", you can permanently erase your profile and all associated data across LinkHop, BioHop, PixelHop, and NoteHop with zero recovery delay.') }}</p>
                </details>

                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What languages does HOP support?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('HOP officially supports 8 languages: English (default), Bahasa Indonesia, Chinese (中文), Spanish (Español), Arabic (العربية with full RTL support), Portuguese (Português), French (Français), and Japanese (日本語). NoteHop additionally supports Russian (Русский).') }}</p>
                </details>

                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Can I self-host HOP on my own server?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Yes! Every app is open-source under the MIT license. You can clone the repositories and run the hub and applications behind nginx with a shared database. Documentation is available in our GitHub repositories.') }}</p>
                </details>
            </dl>
        </section>

        {{-- Section 2: LinkHop --}}
        <section id="linkhop" class="space-y-4 scroll-mt-24">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-indigo-500"></span>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">LinkHop</h2>
                </div>
                <a href="https://link.hel.ink/faq" class="text-xs font-semibold text-indigo-600 underline underline-offset-2 dark:text-indigo-400">{{ __('Full LinkHop FAQ') }} &rarr;</a>
            </div>
            <dl class="space-y-3">
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Why do short links live at hel.ink/l/{slug}?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('LinkHop uses the /l/ prefix to cleanly isolate short links from bio pages (/b/) and hub marketing pages. Legacy short links without /l/ automatically redirect during the transition period.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Can I choose a custom slug and password-protect my link?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Yes. When shortening a link, you can specify a custom alias slug and optionally configure a password gate so only people with the passcode can be redirected.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What analytics are recorded when someone clicks my link?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('LinkHop records total clicks, top referrers, device breakdown (mobile vs. desktop), browser families, and countries. IP addresses are hashed and never shown to link owners.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How does LinkHop prevent malware and phishing?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Destination URLs are checked against security blocklists and automated heuristic filters. Flagged destinations are blocked instantly, and visitors can report suspicious links via the "Report abuse" form.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Can I use LinkHop via API or ShareX?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Yes. Scoped API tokens generated in your HOP ID settings can be used with our REST API (link.hel.ink/api/v1) and with popular automation tools like ShareX.') }}</p>
                </details>
            </dl>
        </section>

        {{-- Section 3: BioHop --}}
        <section id="biohop" class="space-y-4 scroll-mt-24">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-purple-500"></span>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">BioHop</h2>
                </div>
                <a href="https://bio.hel.ink/faq" class="text-xs font-semibold text-purple-600 underline underline-offset-2 dark:text-purple-400">{{ __('Full BioHop FAQ') }} &rarr;</a>
            </div>
            <dl class="space-y-3">
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How do bio pages work at hel.ink/b/{slug}?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('BioHop lets you build a personalized profile page under hel.ink/b/yourname to aggregate your links, social media handles, portfolio, media embeds, and contact details.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What types of blocks can I add to my bio page?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('You can add standard link buttons, rich media embeds (YouTube, Spotify, Soundcloud), interactive maps, image galleries, vCard contact cards, and text notes in any order.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Are themes and custom CSS supported?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('BioHop includes over 20 pre-built themes with light/dark styles, background gradients, and animated button effects. You can also customize colors, fonts, and apply custom CSS.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Can I password-protect or age-gate my page?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Yes. You can add a passcode gate to keep your bio private, or toggle an 18+ content disclaimer warning visitors before sensitive material is displayed.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Are bio page usernames separate from LinkHop link slugs?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Yes! LinkHop short links (/l/name) and BioHop profiles (/b/name) exist in separate namespaces. Both can share the same slug without collision.') }}</p>
                </details>
            </dl>
        </section>

        {{-- Section 4: PixelHop --}}
        <section id="pixelhop" class="space-y-4 scroll-mt-24">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-cyan-500"></span>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">PixelHop</h2>
                </div>
                <a href="https://pixel.hel.ink/faq" class="text-xs font-semibold text-cyan-600 underline underline-offset-2 dark:text-cyan-400">{{ __('Full PixelHop FAQ') }} &rarr;</a>
            </div>
            <dl class="space-y-3">
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What media files can I host on PixelHop?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('PixelHop supports photos (PNG, JPG, WebP, GIF, SVG), short video clips (MP4, WebM), audio recordings, and documents (PDF) with fast delivery and responsive previews.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What is the difference between pixel.hel.ink and p.hel.ink?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('pixel.hel.ink is the application interface where you log in, upload, manage albums, and edit photos. p.hel.ink is the dedicated public content domain serving raw media and shared views (p.hel.ink/{slug}).') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Does PixelHop strip GPS location and EXIF metadata?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Yes! For your privacy and safety, all GPS coordinates, camera serial numbers, and personal device metadata are automatically stripped before public file delivery.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How do albums and direct links work?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('You can group multiple files into an album with a single shareable link, or copy direct image URLs (/i/slug.png) for embedding in forums, chats, and markdown documents.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How do I configure ShareX or upload via the API?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('PixelHop provides a downloadable .sxcu configuration file directly from your user dashboard. You can upload screenshots with one click straight from your desktop.') }}</p>
                </details>
            </dl>
        </section>

        {{-- Section 5: NoteHop --}}
        <section id="notehop" class="space-y-4 scroll-mt-24">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">NoteHop</h2>
                </div>
                <a href="https://note.hel.ink/faq" class="text-xs font-semibold text-emerald-600 underline underline-offset-2 dark:text-emerald-400">{{ __('Full NoteHop FAQ') }} &rarr;</a>
            </div>
            <dl class="space-y-3">
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('How does client-side end-to-end encryption work in NoteHop?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('Note text and file attachments are encrypted client-side in your web browser using Web Crypto AES-GCM before transmission. The decryption key is generated locally and stored exclusively in the URL hash fragment (#k=...).') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Does the server ever see my unencrypted note or key?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('No. Web browsers never transmit the # fragment to web servers. The server only receives and stores unreadable ciphertext. It is mathematically impossible for server operators to read your notes.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('Can the server recover my note if I lose the link or key?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('No. Because NoteHop operates under a zero-knowledge architecture, no master keys exist. If you lose the link containing the key, the note cannot be recovered by anyone.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What are self-destructing and burn-after-reading notes?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('You can configure a note to permanently erase itself after a single view ("Burn after reading"), or set an expiration timer (from 1 hour to 30 days). Expired notes are completely purged from storage.') }}</p>
                </details>
                <details class="{{ $card }}">
                    <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">{{ __('What is the difference between note.hel.ink and n.hel.ink?') }}</summary>
                    <p class="mt-3 text-sm leading-relaxed {{ $muted }}">{{ __('note.hel.ink is the web application where you write, view, and collaborate on notes. n.hel.ink is the lightweight short redirect domain that keeps URLs compact while preserving the cryptographic fragment (n.hel.ink/slug#k=...).') }}</p>
                </details>
            </dl>
        </section>

        {{-- Footer Help Link --}}
        <div class="rounded-2xl border border-slate-200/80 bg-slate-100/50 p-6 text-center text-sm dark:border-white/10 dark:bg-white/5">
            <h3 class="font-bold text-slate-900 dark:text-white">{{ __('Still have questions?') }}</h3>
            <p class="mt-1 {{ $muted }}">{{ __('Explore our detailed guides in the Help Center or get in touch directly.') }}</p>
            <div class="mt-4 flex justify-center gap-3">
                <a href="{{ route('help') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white dark:bg-white dark:text-slate-900">{{ __('Help Center') }}</a>
                <a href="{{ route('contact') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 dark:border-white/10 dark:text-white/80">{{ __('Contact Us') }}</a>
            </div>
        </div>
    </section>
</x-marketing-layout>
