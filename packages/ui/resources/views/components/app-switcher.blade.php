@props(['app' => null])
<details class="hop-switcher" data-hop-switcher>
    <summary aria-label="{{ __('HOP Family') }}" title="{{ __('HOP Family') }}">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="15" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="15" width="6" height="6" rx="1.5"/><rect x="15" y="15" width="6" height="6" rx="1.5"/></svg>
    </summary>
    <nav class="hop-switcher-panel" aria-label="{{ __('HOP Family') }}">
        <strong>{{ __('HOP Family') }}</strong>
        @foreach (\Hop\Ui\Assets::family($app) as $familyApp)
            <a href="{{ $familyApp['url'] }}" @if($familyApp['current']) aria-current="true" @endif>
                <img src="{{ $familyApp['icons']['mark']['url'] }}" width="32" height="32" alt="" loading="lazy">
                <span>{{ $familyApp['name'] }}</span>
            </a>
        @endforeach
    </nav>
</details>
<x-hop::switcher-assets />
