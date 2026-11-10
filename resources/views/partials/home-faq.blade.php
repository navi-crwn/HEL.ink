{{-- Good questions: one card each, with a small picture that moves when the card is in view. --}}
<section id="faq" class="hx" aria-labelledby="faq-title">
    <div class="hx-wrap">
        <div class="hx-head"><h2 id="faq-title"><span>{{ __('Good questions.') }} <em>{{ __('Simple answers.') }}</em></span></h2><p class="h-sub"><span>{{ __('Start here, then make something yours.') }}</span></p></div>
        <div class="hx-faq hop-bento" data-hx-faq>
            <article class="hx-cell hx-q" data-spot><div class="hx-ico ico-free" aria-hidden="true"><i></i><i></i><i></i></div><h3>{{ __('Is HOP free?') }}</h3><p>{{ __('Yes. The apps are free and open source.') }}</p></article>
            <article class="hx-cell hx-q" data-spot><div class="hx-ico ico-one" aria-hidden="true"><i></i><i></i><i></i><i></i></div><h3>{{ __('Do I need an account?') }}</h3><p>{{ __('One HOP ID works across every app. Some tools also work without signing in.') }}</p></article>
            <article class="hx-cell hx-q" data-spot><div class="hx-ico ico-lock" aria-hidden="true"><svg viewBox="0 0 48 56"><path class="shackle" d="M14 24V16a10 10 0 0 1 20 0v8" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><rect x="6" y="24" width="36" height="28" rx="8" fill="currentColor"/></svg></div><h3>{{ __('Can the server read my note?') }}</h3><p>{{ __('Not an encrypted note, which is the default: its cryptographic key is in the URL hash fragment, which browsers never send to the server. A basic note is readable by the server.') }}</p></article>
            <article class="hx-cell hx-q" data-spot><div class="hx-ico ico-chain" aria-hidden="true"><i></i><i></i><i></i><i></i></div><h3>{{ __('What is chained logout?') }}</h3><p>{{ __('When you sign out of one HOP app, the hub signs you out of the others too. Nothing stays open on a shared computer.') }}</p></article>
            <article class="hx-cell hx-q" data-spot><div class="hx-ico ico-data" aria-hidden="true"><i></i><i></i><i></i></div><h3>{{ __('Can I take my data and leave?') }}</h3><p>{{ __('Yes. Export everything from Settings, or delete your account. Deleting removes your data from every app.') }}</p></article>
            <article class="hx-cell hx-q" data-spot><div class="hx-ico ico-host" aria-hidden="true"><i></i><i></i><i></i></div><h3>{{ __('Can I self-host?') }}</h3><p>{{ __('Yes. Read the installation guide in each repository.') }}</p></article>
        </div>
        <p class="hx-help">{{ __('Where can I get help?') }} {{ __('Each app has a help page. You can also report issues on GitHub.') }}</p>
    </div>
</section>
