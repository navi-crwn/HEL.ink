{{-- Sidebar items for the other HOP apps (for an existing <ul>, LinkHop/hub sidebar classes). --}}
@props(['except' => []])
@foreach (\Hop\Ui\Apps::all() as $app)
    @continue($app['current'] || in_array($app['slug'], (array) $except, true))
    <li>
        <a href="{{ $app['open'] }}" class="nav-item">
            <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ \Hop\Ui\Apps::icon($app['icon']) }}"/></svg>
            <span class="flex-1">{{ $app['name'] }}</span>
            <svg class="h-3.5 w-3.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M8 7h9v9"/></svg>
        </a>
    </li>
@endforeach
