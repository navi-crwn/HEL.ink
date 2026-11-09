{{-- The shared HOP dialogs (HopUI.alert/confirm/prompt) and reminder cards ([data-hop-remind]) from
     hel.ink/hop-assets. The asset manifest lists them as ui_css and ui_js from the version that has
     them on; with an older manifest nothing is rendered here and the hub asks through the browser's
     own dialogs and its own "Hide" button (resources/js/dialogs.js, resources/js/reminders.js).
     Same tags as hop/ui's <x-hop::ui /> component, which the other apps use. In <head>, after the fonts. --}}
@php
    $hopUiCss = \Hop\Ui\Assets::asset('ui_css');
    $hopUiJs = \Hop\Ui\Assets::asset('ui_js');
    $hopUiNonce = \Hop\Ui\Assets::nonce();
@endphp
@if ($hopUiCss && $hopUiJs)
    <link rel="stylesheet" href="{{ $hopUiCss['url'] }}" integrity="{{ $hopUiCss['integrity'] }}" crossorigin="anonymous">
    <script src="{{ $hopUiJs['url'] }}" integrity="{{ $hopUiJs['integrity'] }}" crossorigin="anonymous" defer @if ($hopUiNonce) nonce="{{ $hopUiNonce }}" @endif></script>
@endif
