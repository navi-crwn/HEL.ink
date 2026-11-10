@php
    $icons = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2-1.2L14.5 3h-5l-.4 2.6a7.6 7.6 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7.6 7.6 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z',
        'bell' => 'M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1',
        'pulse' => 'M3 12h4l3-8 4 16 3-8h4',
    ];
    $items = array_filter([
        ['label' => __('Launcher'), 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => __('Announcements'), 'icon' => 'bell', 'route' => 'announcements.index', 'active' => 'announcements.*'],
        ['label' => __('Status'), 'icon' => 'pulse', 'route' => 'status', 'active' => 'status'],
        ['label' => __('Settings'), 'icon' => 'settings', 'route' => 'settings', 'active' => ['settings', 'profile.*']],
    ]);
@endphp
<ul class="space-y-1">
    @foreach ($items as $item)
        <li>
            <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs(...(array) $item['active']) ? 'nav-item-active' : '' }}">
                <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] }}"/></svg>
                <span class="flex-1">{{ $item['label'] }}</span>
            </a>
        </li>
    @endforeach
</ul>
{{-- The apps: one list in the sidebar, from the shared manifest; the top bar keeps settings-type controls only. --}}
<x-hop::family-nav app="hub" :label="__('HOP family')" />
