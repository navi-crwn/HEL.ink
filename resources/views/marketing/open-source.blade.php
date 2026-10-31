<x-marketing-layout :title="__('Open source').' · HOP'" :themed="true">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ __('Open source') }}</h1>
        <div class="mt-6 space-y-4 text-slate-700 dark:text-white/80">
            <p>{!! __('Every HOP app is free software under the <strong>MIT license</strong>: read it, change it, run it, share it.') !!}</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('The repositories') }}</h2>
            <ul class="list-disc space-y-1 ps-5" dir="ltr">
                <li><a class="underline" href="https://github.com/navi-crwn/HEL.ink">navi-crwn/HEL.ink</a>: {{ __('the hub, HOP ID and this site (Laravel).') }}</li>
                @foreach ($stories as $app)
                    @if ($app['repo'])
                        <li><a class="underline" href="{{ $app['repo'] }}">{{ \Illuminate\Support\Str::after($app['repo'], 'github.com/') }}</a>: {{ $app['kicker'] }}.</li>
                    @endif
                @endforeach
                <li><a class="underline" href="https://github.com/navi-crwn/hop-auth">hop-auth</a>, <a class="underline" href="https://github.com/navi-crwn/hop-ui">hop-ui</a>, <a class="underline" href="https://github.com/navi-crwn/hop-safety">hop-safety</a>: {{ __('the shared packages.') }}</li>
            </ul>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Run your own') }}</h2>
            <p>{{ __('The Laravel apps (hub, LinkHop, BioHop) share one database and run behind one nginx; PixelHop and NoteHop are each one Go binary with one SQLite file. Each README has a self-hosting guide.') }}</p>
            <h2 class="pt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ __('Contribute') }}</h2>
            <p>{{ __('Bug reports and ideas: open an issue on the app\'s repository. Fixes: pull requests are welcome. Security problems: a private security advisory, please.') }}</p>
        </div>
    </section>
</x-marketing-layout>
