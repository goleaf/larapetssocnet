<x-guest-layout>
 <div class="space-y-5" data-ui="login-security-alert-action">
 <div class="text-center">
 <div class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-paw-dark" aria-hidden="true">
 <x-heroicon-o-shield-check class="h-7 w-7"/>
 </div>

 @if ($status === \App\Actions\Auth\ConsumeLoginSecurityAlertAction::STATUS_ALREADY_USED)
 <h1 class="mt-4 shell-title text-2xl text-balance">This security alert was already handled</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">
 The login alert link has already been used. No additional action was taken.
 </p>
 @elseif ($mode === 'dismiss')
 <h1 class="mt-4 shell-title text-2xl text-balance">Thanks for confirming</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">
 We marked this login as recognized. Your account sessions were left unchanged.
 </p>
 @else
 <h1 class="mt-4 shell-title text-2xl text-balance">Your account sessions were secured</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">
 We invalidated active sessions, cleared persistent logins, and alerted PetSocial administrators for review.
 </p>
 @endif
 </div>

 <x-ui.alert type="{{ $mode === 'secure' ? 'warning' : 'success' }}">
 Keep this email for your records. Support may ask you to confirm when the login alert was triggered.
 </x-ui.alert>

 <div class="border-t border-whisker/30 pt-5">
 <a href="{{ route('login') }}" class="btn-base btn-primary h-[var(--control-height-md)] min-h-11 w-full px-5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Back to login
 </a>
 </div>
 </div>
</x-guest-layout>
