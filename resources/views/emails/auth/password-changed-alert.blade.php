<x-mail::message>
<div style="text-align: center; margin-bottom: 24px;">
<div style="display: inline-block; padding: 12px 16px; border-radius: 18px; background: #f8ead7; color: #7a4b2b; font-weight: 700;">
🐾 PetSocial
</div>
</div>

# Your password was changed

Hi {{ $user->name }},

The password for your PetSocial account was changed on {{ $changedAt->format('F j, Y \a\t g:i A T') }}. All existing sessions and persistent logins were invalidated.

If you made this change, no action is needed.

<x-mail::button :url="$emergencyUrl" color="error">
This was not me
</x-mail::button>

Use the button above only if you did not change your password. It will immediately lock your account and alert PetSocial administrators for review.

If the button does not work, copy and paste this link into your browser:

{{ $emergencyUrl }}

Thanks,<br>
The PetSocial team
</x-mail::message>
