<button
 {{ $attributes->merge(['type'=>'submit','class'=>'btn-base btn-danger']) }}
>
 {{ $slot }}
</button>
