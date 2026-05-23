<x-mail::message>
<div style="text-align: center; margin-bottom: 24px;">
<div style="display: inline-block; padding: 12px 16px; border-radius: 18px; background: #f8ead7; color: #7a4b2b; font-weight: 700;">
🐾 PetSocial
</div>
</div>

# Reset your password, {{ $user->name }}.

We received a request to reset the password for your PetSocial account. This link expires in 60 minutes and can only be used with the email address that requested it.

<x-mail::button :url="$resetUrl" color="success">
Reset my password
</x-mail::button>

If the button does not work, copy and paste this link into your browser:

{{ $resetUrl }}

If you did not request this, you can ignore this email.

Thanks,<br>
The PetSocial team
</x-mail::message>
