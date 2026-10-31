{{-- HOP family typography (Inter, Instrument Serif, JetBrains Mono), self-hosted on hel.ink.
     Put in <head>; then use var(--hop-font-display|body|mono) or the app's Tailwind tokens. --}}
@php($css = \Hop\Ui\Assets::asset('fonts_css'))
@if ($css)
    <link rel="preconnect" href="https://hel.ink" crossorigin>
    <link rel="stylesheet" href="{{ $css['url'] }}" integrity="{{ $css['integrity'] }}" crossorigin="anonymous">
@endif
