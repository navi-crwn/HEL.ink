<div class="identity-hero" aria-hidden="true">
    <div class="identity-orbit"></div>
    <div class="identity-core"><img src="{{ collect(\Hop\Ui\Assets::family('hub'))->firstWhere('slug', 'hub')['icons']['mark']['url'] }}" alt="" width="112" height="112"><strong>HOP ID</strong><span>hel.ink</span></div>
    @foreach(\Hop\Ui\Assets::family('hub') as $app)
        @if(!$app['current'])
            <div class="identity-satellite identity-{{ $app['slug'] }}"><img src="{{ $app['icons']['mark']['url'] }}" alt="" width="42" height="42"><span>{{ $app['name'] }}</span></div>
        @endif
    @endforeach
</div>
