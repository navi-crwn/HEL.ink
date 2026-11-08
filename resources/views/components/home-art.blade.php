@props(['preset', 'name' => null, 'eager' => false])
<figure class="h-art">
    <picture><source srcset="{{ asset('hop-assets/v5/home/'.$preset.'-dark.webp') }}" media="(prefers-color-scheme: dark)"><img src="{{ asset('hop-assets/v5/home/'.$preset.'-light.webp') }}" alt="{{ __('Interactive :app logo', ['app' => $name ?? $preset]) }}" width="460" height="460" loading="{{ $eager ? 'eager' : 'lazy' }}" fetchpriority="{{ $eager ? 'high' : 'auto' }}"></picture>
    <canvas data-hop-hero="{{ $preset }}" tabindex="0" aria-label="{{ __('Drag or use arrow keys to rotate') }}"></canvas>
</figure>
