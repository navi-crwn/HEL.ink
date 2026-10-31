{{-- "Back" link for inner pages: to the given address (default: this app's home). --}}
@props(['href' => null, 'label' => 'Back'])
<a href="{{ $href ?? url('/') }}" {{ $attributes->merge(['class' => 'hop-back']) }}>
    <span aria-hidden="true">&larr;</span> {{ $label }}
</a>
