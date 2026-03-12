<section>
 <header>
 <h2 class="text-lg font-medium text-gray-900">
 {{ __('Profile Information') }}
 </h2>

 <p class="mt-1 text-sm text-gray-600">
 {{ __("Update your account's profile information and email address.") }}
 </p>
 </header>

 <form id="send-verification" method="post" action="{{ route('verification.send') }}">
 @csrf
 </form>

 <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
 @csrf
 @method('patch')

 <div>
 <x-ui.input id="name" name="name" type="text" :label="__('Name')" :value="old('name', $user->name)" required autofocus autocomplete="name"/>
 </div>

 <div>
 <x-ui.input id="email" name="email" type="email" :label="__('Email')" :value="old('email', $user->email)" required autocomplete="username"/>

 @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
 <div>
 <p class="text-sm mt-2 text-gray-800">
 {{ __('Your email address is unverified.') }}

 <x-ui.button form="send-verification" variant="ghost" size="sm">
 {{ __('Click here to re-send the verification email.') }}
 </x-ui.button>
 </p>

 @if (session('status') ==='verification-link-sent')
 <p class="mt-2 font-medium text-sm text-green-600">
 {{ __('A new verification link has been sent to your email address.') }}
 </p>
 @endif
 </div>
 @endif
 </div>

 <div class="flex items-center gap-4">
 <x-ui.button variant="primary">{{ __('Save') }}</x-ui.button>

 @if (session('status') ==='profile-updated')
 <p
 x-data="{ show: true }"
 x-show="show"
 x-transition
 x-init="setTimeout(() => show = false, 2000)"
 class="text-sm text-gray-600"
 >{{ __('Saved.') }}</p>
 @endif
 </div>
 </form>
</section>
