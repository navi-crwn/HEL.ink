{{-- HOP dialogs (HopUI.alert/confirm/prompt) and reminder cards ([data-hop-remind]) from hel.ink.
     Put once in the layout, after the fonts. CSP needs https://hel.ink in script-src, style-src
     and connect-src is not needed (the card posts to the app itself). --}}
@php($css = \Hop\Ui\Assets::asset('ui_css'))
@php($js = \Hop\Ui\Assets::asset('ui_js'))
@php($nonce = \Hop\Ui\Assets::nonce())
@if ($css && $js)
    @once
        <link rel="stylesheet" href="{{ $css['url'] }}" integrity="{{ $css['integrity'] }}" crossorigin="anonymous">
        <script src="{{ $js['url'] }}" integrity="{{ $js['integrity'] }}" crossorigin="anonymous" defer @if ($nonce) nonce="{{ $nonce }}" @endif></script>
    @endonce
@endif
