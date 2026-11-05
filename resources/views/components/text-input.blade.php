@props(['disabled' => false])
<input @disabled($disabled) {{ $attributes->filter(fn ($value) => ! is_array($value))->merge(['class' => 'input']) }}>
