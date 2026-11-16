@php $muted = 'text-slate-600 dark:text-white/70'; @endphp
<x-marketing-layout :title="__('Thank you').' · HOP'" :description="__('Your feedback was sent to HOP.')" :themed="true" :noindex="true" :back="false">
    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6 sm:py-20">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('Thank you') }}</h1>
        <p class="mt-3 text-lg {{ $muted }}">{{ __('Your feedback was sent. We read every report but cannot answer each one.') }}</p>
        @if ($noRoom)
            <p class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100" role="status">{{ __('Your text was saved, but the screenshot could not be kept because the space for screenshots is full.') }}</p>
        @endif
        <div class="mt-8 flex flex-wrap gap-3">
            @if ($back)
                <a href="{{ $back }}" class="btn-primary btn-lg">{{ __('Back to :app', ['app' => $name]) }}</a>
            @endif
            <a href="{{ route('feedback.create', ['app' => $app]) }}" class="btn-secondary btn-lg">{{ __('Send more feedback') }}</a>
        </div>
    </section>
</x-marketing-layout>
