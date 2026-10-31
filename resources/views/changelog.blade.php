<x-marketing-layout :title="__('Changelog')" :themed="true">
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
        <h1 class="text-4xl font-bold">{{ __('Changelog') }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-300">{{ __('Commits, releases and merged pull requests, newest first.') }}</p>
        <nav class="my-6 flex flex-wrap gap-3" aria-label="{{ __('HOP Family') }}">
            <a href="{{ request()->fullUrlWithQuery(['app' => null, 'page' => 1]) }}" @if($selected === '') aria-current="page" @endif>{{ __('All apps') }}</a>
            @foreach($apps as $app)<a href="{{ request()->fullUrlWithQuery(['app' => $app, 'page' => 1]) }}" @if($selected === $app) aria-current="page" @endif>{{ $app }}</a>@endforeach
        </nav>
        @include('partials.changelog-entries')
    </section>
</x-marketing-layout>
