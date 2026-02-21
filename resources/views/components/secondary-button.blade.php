<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'btn-base btn-secondary']) }}
>
    {{ $slot }}
</button>
