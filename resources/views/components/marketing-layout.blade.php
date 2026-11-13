@props(['title' => null, 'description' => null, 'themed' => false, 'back' => true, 'alternates' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" @class(['h-full', 'dark' => ! $themed])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $metaTitle = $title ?? config('app.name');
            $metaDescription = $description ?? __('Short links, a page for your links, images, private notes and file conversion. One free HOP ID connects them all.');
            $metaKeywords = 'hop, hel.ink, linkhop, biohop, pixelhop, notehop, filehop, payhop';
            $ogTitle = $metaTitle;
            $ogDescription = $metaDescription;
            $ogImage = route('brand.logo.dark');
            $favicon = route('brand.favicon');
            $canonical = \App\Support\Seo::canonical(request());
        @endphp
        <title>{{ $metaTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="keywords" content="{{ $metaKeywords }}">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ \App\Support\Seo::NAME }}">
        <meta name="twitter:card" content="summary">
        <link rel="canonical" href="{{ $canonical }}">
        <link rel="icon" href="{{ $favicon }}">
        @if ($themed)
            @include('partials.hop-theme')
        @endif
        {{-- Every public page exists in the nine languages; `alternates` is kept so older callers still pass it. --}}
        @foreach (\App\Support\Seo::alternates(request()->path()) as $code => $href)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
        @endforeach
        <x-hop::fonts />
        @vite(['resources/css/app.css', 'resources/css/coss.css', 'resources/js/app.js'])
        @include('partials.hop-bento')
        @stack('head')
    </head>
    @if ($themed)
    <body class="min-h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-[#0e1322] dark:text-slate-100">
        <x-session-expired-notice />
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-40 -top-48 h-136 w-136 rounded-full bg-indigo-300/40 blur-[120px] dark:bg-indigo-500/20"></div>
            <div class="absolute -right-32 top-10 h-112 w-md rounded-full bg-sky-200/50 blur-[120px] dark:bg-sky-500/10"></div>
            <div class="absolute -bottom-56 left-1/3 h-120 w-120 rounded-full bg-fuchsia-200/40 blur-[130px] dark:bg-fuchsia-500/10"></div>
        </div>
    @else
    <body class="min-h-full bg-[#070a17] font-sans text-white antialiased">
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-40 -top-40 h-136 w-136 rounded-full bg-indigo-600/30 blur-[120px]"></div>
            <div class="absolute -right-32 top-20 h-112 w-md rounded-full bg-sky-500/20 blur-[120px]"></div>
            <div class="absolute -bottom-48 left-1/3 h-120 w-120 rounded-full bg-fuchsia-600/20 blur-[130px]"></div>
        </div>
    @endif
        @include('partials.marketing-nav', ['themed' => $themed])
        <main id="main">
            @if ($back)
                <div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 transition hover:text-slate-900 dark:text-white/60 dark:hover:text-white">
                        <span aria-hidden="true" class="inline-block rtl:-scale-x-100">&larr;</span> {{ __('Back to HOP') }}
                    </a>
                </div>
            @endif
            {{ $slot }}
        </main>
        @include('partials.marketing-footer')
        @stack('scripts')
    </body>
</html>
