<x-mail::message>
<div style="text-align: center; margin-bottom: 24px;">
<div style="display: inline-block; padding: 12px 16px; border-radius: 18px; background: #f8ead7; color: #7a4b2b; font-weight: 700;">
🐾 PetSocial
</div>
</div>

# Welcome to PetSocial, {{ $user->name }}.

Please verify your email address so we can protect your account, keep recovery options secure, and make sure important account messages reach you.

Clicking the button below will confirm that this email address belongs to you and finish your account verification.

<x-mail::button :url="$verificationUrl" color="success">
Verify my email
</x-mail::button>

If the button does not work, copy and paste this link into your browser:

{{ $verificationUrl }}

Thanks,<br>
The PetSocial team
</x-mail::message>
