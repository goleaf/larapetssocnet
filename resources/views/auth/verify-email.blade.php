<x-guest-layout>
 <x-ui.alert type="info" class="mb-4">
 {{ __('en.thanks_for_signing_up_before_getting_started_could_you_verify_your_email_address') }}
 </x-ui.alert>

 @if (session('status') =='verification-link-sent')
 <x-ui.alert type="success" class="mb-4">
 {{ __('en.a_new_verification_link_has_been_sent_to_the_email_address_you_provided_during_r') }}
 </x-ui.alert>
 @endif

 <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
 <form method="POST" action="{{ route('verification.send') }}">
 @csrf
 <x-ui.button type="submit" variant="primary" size="sm">{{ __('en.resend_verification_email') }}</x-ui.button>
 </form>

 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <x-ui.button type="submit" variant="ghost" size="sm">{{ __('en.log_out') }}</x-ui.button>
 </form>
 </div>
</x-guest-layout>
