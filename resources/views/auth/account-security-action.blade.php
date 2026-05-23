<x-guest-layout>
 <div class="space-y-5" data-ui="account-security-action">
 <div class="text-center">
 <div class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-[var(--radius-soft)] bg-rose-light text-rose" aria-hidden="true">
 <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
 <path d="M12 3 4.5 6.2v5.7c0 4.3 3 7.6 7.5 9.1 4.5-1.5 7.5-4.8 7.5-9.1V6.2L12 3Z"></path>
 <path d="M12 8v5"></path>
 <path d="M12 16h.01"></path>
 </svg>
 </div>

 @if ($status === \App\Actions\Auth\ConsumeSecurityEmergencyAction::STATUS_ALREADY_USED)
 <h1 class="mt-4 shell-title text-2xl text-balance">This security action was already taken</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">
 Your account lock request was already processed. PetSocial administrators will review the account before access is restored.
 </p>
 @else
 <h1 class="mt-4 shell-title text-2xl text-balance">Your account has been locked</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">
 We suspended sign-in and invalidated active sessions. PetSocial administrators have been alerted for review.
 </p>
 @endif
 </div>

 <x-ui.alert type="warning">
 Keep this email for your records. Support may ask you to confirm when the security alert was triggered.
 </x-ui.alert>

 <div class="border-t border-whisker/30 pt-5">
 <a href="{{ route('login') }}" class="btn-base btn-primary h-[var(--control-height-md)] min-h-11 w-full px-5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Back to login
 </a>
 </div>
 </div>
</x-guest-layout>
