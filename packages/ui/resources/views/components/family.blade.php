{{-- The HOP family: every public app with its official mark, tagline and link, from the
     hub's manifest. The current app is marked and not linked. Styling is the app's own
     (pass classes); the markup uses plain semantic elements. --}}
@props(['current' => null, 'title' => 'The HOP family', 'intro' => 'One HOP ID signs you in to every app.'])
@php($apps = \Hop\Ui\Assets::family($current))
<section {{ $attributes->merge(['class' => 'hop-family']) }} aria-labelledby="hop-family-title">
    <h2 id="hop-family-title" class="hop-family-title">{{ $title }}</h2>
    @if ($intro)<p class="hop-family-intro">{{ $intro }}</p>@endif
    <ul class="hop-family-list" role="list">
        @foreach ($apps as $app)
            <li class="hop-family-item{{ $app['current'] ? ' is-current' : '' }}">
                @if ($app['current'])
                    <span class="hop-family-link" aria-current="page">
                @else
                    <a class="hop-family-link" href="{{ $app['url'] }}">
                @endif
                    <img class="hop-family-mark" src="{{ $app['icons']['mark']['url'] }}" alt="" width="40" height="40" loading="lazy">
                    <span class="hop-family-name">{{ $app['name'] }}</span>
                    <span class="hop-family-tagline">{{ $app['tagline'] }}</span>
                @if ($app['current'])
                    </span>
                @else
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</section>
