@props(['preset', 'eager' => false, 'fallback' => null])
@php
    // Apps added after hop-assets v2 (FileHop) have no 3D tile or hero image there: show their screenshot.
    $tiled = is_file(public_path('hop-assets/v2/home/'.$preset.'-light.webp'));
@endphp
@if (! $tiled && $fallback)
<figure class="h-art h-art-shot"><img src="{{ $fallback }}" alt="" width="1280" height="900" loading="lazy" decoding="async"></figure>
@else
<figure class="h-art">
    <picture><source srcset="{{ asset('hop-assets/v2/home/'.$preset.'-dark.webp') }}" media="(prefers-color-scheme: dark)"><img src="{{ asset('hop-assets/v2/home/'.$preset.'-light.webp') }}" alt="{{ __('Interactive :app logo', ['app' => $preset]) }}" width="460" height="460" loading="{{ $eager ? 'eager' : 'lazy' }}" fetchpriority="{{ $eager ? 'high' : 'auto' }}"></picture>
    <canvas data-hop-hero="{{ $preset }}" tabindex="0" aria-label="{{ __('Drag or use arrow keys to rotate') }}"></canvas>
</figure>
@endif
