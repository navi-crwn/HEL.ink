{{-- Floating HOP tiles, the same six tiles in every app (drawn by hel.ink's hop-float.js).
     mode: "fixed" (behind the page, default) or "section" (inside a position:relative box). --}}
@props(['mode' => 'fixed'])
@php($css = \Hop\Ui\Assets::asset('float_css'))
@php($js = \Hop\Ui\Assets::asset('float_js'))
@php($nonce = \Hop\Ui\Assets::nonce())
@if ($css && $js)
    @once
        <link rel="stylesheet" href="{{ $css['url'] }}" integrity="{{ $css['integrity'] }}" crossorigin="anonymous">
        <script src="{{ $js['url'] }}" integrity="{{ $js['integrity'] }}" crossorigin="anonymous" defer @if ($nonce) nonce="{{ $nonce }}" @endif></script>
    @endonce
    <div data-hop-float="{{ $mode === 'section' ? 'section' : '' }}" {{ $attributes }}></div>
@endif
