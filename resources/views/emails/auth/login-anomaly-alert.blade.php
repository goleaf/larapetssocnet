<x-mail::message>
<div style="text-align: center; margin-bottom: 24px;">
<div style="display: inline-block; padding: 12px 16px; border-radius: 18px; background: #f8ead7; color: #7a4b2b; font-weight: 700;">
🐾 PetSocial
</div>
</div>

# New login from {{ $alert->country }}

Hi {{ $user->name }},

We noticed a successful login to your PetSocial account from a country not seen in your recent login history.

<x-mail::panel>
**Location:** {{ $alert->city ? $alert->city.', '.$alert->country : $alert->country }}  
**Device:** {{ ucfirst($alert->device_type) }}  
**Browser:** {{ trim($alert->browser_name.' '.($alert->browser_version ?? '')) }}  
**Operating system:** {{ trim($alert->os_name.' '.($alert->os_version ?? '')) }}  
**Time:** {{ $alert->login_at->format('F j, Y \a\t g:i A T') }}
</x-mail::panel>

If this was you, dismiss the alert. If it was not you, secure your account immediately.

<x-mail::button :url="$dismissUrl" color="success">
This was me, everything is fine
</x-mail::button>

<x-mail::button :url="$secureUrl" color="error">
This was NOT me, secure my account
</x-mail::button>

If the buttons do not work, copy and paste these links into your browser:

This was me: {{ $dismissUrl }}

Secure my account: {{ $secureUrl }}

Thanks,<br>
The PetSocial team
</x-mail::message>
