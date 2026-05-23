<x-mail::message>
<div style="text-align: center; margin-bottom: 24px;">
<div style="display: inline-block; padding: 12px 16px; border-radius: 18px; background: #f8ead7; color: #7a4b2b; font-weight: 700;">
🐾 PetSocial
</div>
</div>

# Log in to PetSocial, {{ $user->name }}.

Use this secure link to sign in to your PetSocial account. It expires in 15 minutes and can only be used once.

<x-mail::button :url="$loginUrl" color="success">
Log in to PetSocial
</x-mail::button>

If the button does not work, copy and paste this link into your browser:

{{ $loginUrl }}

If you did not request this link, you can ignore this email.

Thanks,<br>
The PetSocial team
</x-mail::message>
