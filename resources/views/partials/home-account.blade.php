{{-- 01 One account: pinned. The scroll plays how one HOP ID signs you in to every app and out of all of them.
     (resources/js/home.js adds the motion; the finished picture is complete as plain HTML and CSS.) --}}
@php
    $family = collect(\Hop\Ui\Assets::family())->keyBy('slug');
    $hubMark = $family['hub']['icons']['mark']['url'] ?? asset('hop-assets/v2/apps/hub/rabbit-mark.png');
@endphp
<section id="connects" class="hx hx-acct" aria-labelledby="connects-title" data-hx-acct>
    <div class="hx-wrap hx-grid">
        <div class="hx-copy">
            <div class="hx-head"><p class="h-label"><span class="n">01</span> · <span>HOP ID</span></p><h2 id="connects-title"><span>{{ __('One account.') }} <em>{{ __('Connected by design.') }}</em></span></h2></div>
            <ol class="hx-steps" data-hx-steps>
                <li><b>{{ __('Sign in once') }}</b><span>{{ __('One free account, one password. Add two-factor protection when you want it.') }}</span></li>
                <li><b>{{ __('Open any app') }}</b><span>{{ __('The hub hands the app a short-lived ticket. Your password never leaves the hub.') }}</span></li>
                <li><b>{{ __('Keep it safe') }}</b><span>{{ __('Two-factor codes and recovery codes protect every app at once.') }}</span></li>
                <li><b>{{ __('Leave everywhere') }}</b><span>{{ __('Sign out of one app and you are signed out of all of them.') }}</span></li>
            </ol>
        </div>
        <div class="hx-stage" data-signed-out="{{ __('Signed out') }}" data-note-out="{{ __('Signed out once. Signed out everywhere.') }}" aria-hidden="true">
            <svg viewBox="0 0 400 400" preserveAspectRatio="xMidYMid meet"><g class="lines"><path d="M200 200 L118 118"/><path d="M200 200 L282 118"/><path d="M200 200 L118 282"/><path d="M200 200 L282 282"/></g><circle class="pkt" r="6"/></svg>
            <div class="hx-hub"><img src="{{ $hubMark }}" alt="" width="56" height="56"><b>HOP ID</b><span class="hx-code" data-hx-code dir="ltr">482 913</span></div>
            @foreach (collect($stories)->take(4)->values() as $i => $app)
                <div class="hx-node n{{ $i + 1 }}" data-hx-node><img src="{{ $app['mark'] }}" alt="" width="44" height="44"><b>{{ $app['name'] }}</b><i class="st" data-hx-st>{{ __('Signed in') }} ✓</i></div>
            @endforeach
            <div class="hx-ticket" data-hx-ticket><small>{{ __('ticket') }}</small><b dir="ltr">30 s</b></div>
            <p class="hx-note" data-hx-note>{{ __('Signed in once. Every app knows you.') }}</p>
        </div>
    </div>
</section>
