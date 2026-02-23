<button
 {{ $attributes->merge(['type'=>'submit','class'=>'btn-base btn-primary']) }}
>
 {{ $slot }}
</button>
