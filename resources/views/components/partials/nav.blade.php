@props(['isAdmin' => false])
@php
    $icons = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2-1.2L14.5 3h-5l-.4 2.6a7.6 7.6 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7.6 7.6 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z',
        'shield' => 'M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z',
        'bell' => 'M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1',
        'pulse' => 'M3 12h4l3-8 4 16 3-8h4',
        'users' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm-9 10a5 5 0 0 1 10 0M20 8v6m3-3h-6',
    ];
    $items = array_filter([
        ['label' => 'Launcher', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'Announcements', 'icon' => 'bell', 'route' => 'announcements.index', 'active' => 'announcements.*'],
        ['label' => 'Status', 'icon' => 'pulse', 'route' => 'status', 'active' => 'status'],
        ['label' => 'Settings', 'icon' => 'settings', 'route' => 'settings', 'active' => ['settings', 'profile.*']],
    ]);
    $adminItems = $isAdmin ? [
        ['label' => 'Accounts', 'icon' => 'users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
        ['label' => 'Manage announcements', 'icon' => 'bell', 'route' => 'admin.announcements.index', 'active' => 'admin.announcements.*'],
    ] : [];
    $control = (string) config('hop.control_url');
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
    <li class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-muted">Apps</li>
    <x-hop::nav-items />
    @if ($isAdmin)
        <li class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-muted">Admin</li>
        @if ($control !== '')
            <li>
                <a href="{{ $control }}" class="nav-item">
                    <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['shield'] }}"/></svg>
                    <span class="flex-1">Imperium</span>
                    <svg class="h-3.5 w-3.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M8 7h9v9"/></svg>
                </a>
            </li>
        @endif
        @foreach ($adminItems as $item)
            <li>
                <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs(...(array) $item['active']) ? 'nav-item-active' : '' }}">
                    <svg class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] }}"/></svg>
                    <span class="flex-1">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    @endif
</ul>
