{{-- The HOP family in a dashboard sidebar: HOP ID first, then every other public app, with the real marks.
     This is the one place the apps are listed inside a dashboard; the top bar keeps only settings-type controls. --}}
@props(['app' => null, 'label' => null])
@php
    $family = \Hop\Ui\Assets::family($app);
    $configured = collect(\Hop\Ui\Apps::all())->keyBy('slug');
    $hub = rtrim((string) config('hop.hub_url', config('hop-auth.hub_url', 'https://hub.hel.ink')), '/');
@endphp
<p class="mt-5 px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted">{{ $label ?? __('HOP family') }}</p>
<ul {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @foreach ($family as $item)
        @continue($item['current'])
        @php
            // The hub opens its dashboard; an app opens through its SSO start when this app knows one,
            // otherwise through the hub's launcher (/apps/{slug}), which signs the person in there too.
            // The bare app address would land them signed out in an app they have not used yet.
            $href = $item['slug'] === 'hub' ? $hub.'/dashboard' : ($configured[$item['slug']]['open'] ?? $hub.'/apps/'.$item['slug']);
            $name = $item['slug'] === 'hub' ? 'HOP ID' : $item['name'];
        @endphp
        <li>
            <a href="{{ $href }}" class="nav-item">
                <img src="{{ $item['icons']['mark']['url'] }}" alt="" width="18" height="18" loading="lazy" class="h-[18px] w-[18px] shrink-0 rounded-[5px] object-contain">
                <span class="flex-1">{{ $name }}</span>
                <svg class="h-3.5 w-3.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M8 7h9v9"/></svg>
            </a>
        </li>
    @endforeach
</ul>
