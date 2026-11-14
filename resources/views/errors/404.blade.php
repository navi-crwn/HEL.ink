<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-white">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Page not found') }} · HOP</title>
        <link rel="icon" href="{{ route('brand.favicon') }}" type="image/png">
        <x-hop::fonts />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {font-family: 'Inter', sans-serif;}
        </style>
    </head>
    <body class="min-h-full bg-linear-to-br from-slate-950 via-slate-900 to-slate-950 text-white">
        <main class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
            <div class="flex flex-col items-center">
                <img src="{{ route('brand.logo') }}" alt="" class="h-16 w-16 rounded-full shadow-lg">
                <p class="mt-4 text-sm uppercase tracking-[0.4em] text-white/60">HOP</p>
            </div>
            <h1 class="mt-4 text-4xl font-semibold">{{ __('Page not found') }}</h1>
            <p class="mt-2 max-w-md text-white/70">{{ __('This page does not exist or has moved. Check the address or go back to the home page.') }}</p>
            <div class="mt-6 flex flex-wrap items-center gap-4">
                @php [$homeUrl, $homeLabel] = hop_error_home(); @endphp
                <a href="{{ $homeUrl }}" class="rounded-2xl bg-blue-600 px-6 py-3 font-semibold text-white shadow-lg shadow-blue-500/40">{{ $homeLabel }}</a>
            </div>
        </main>
    </body>
</html>
