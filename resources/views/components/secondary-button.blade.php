<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn-outline']) }}>
    {{ $slot }}
</button>
