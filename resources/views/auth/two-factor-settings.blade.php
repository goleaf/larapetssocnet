<x-settings-layout>
 <div class="space-y-8" data-ui="two-factor-settings-page">
 <div class="space-y-2" data-ui="settings-page-header">
 <p class="chip min-h-8">Security</p>
 <h2 class="shell-title text-2xl">Two-factor authentication</h2>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">
 Add an authenticator code before your account can reach protected PetSocial pages.
 </p>
 </div>

 @if (session('success'))
 <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
 @endif

 @if (session('recovery_codes'))
 <x-ui.alert type="warning" title="Save these recovery codes">
 <ul class="mt-2 grid gap-1 font-mono text-sm">
 @foreach (session('recovery_codes') as $code)
 <li>{{ $code }}</li>
 @endforeach
 </ul>
 </x-ui.alert>
 @endif

 <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-start">
 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/40 p-4" data-ui="two-factor-qr-code-panel">
 <div class="mx-auto aspect-square w-full max-w-60 text-bark">
 {!! $qrCode !!}
 </div>
 <p class="mt-3 break-all text-center font-mono text-xs text-fur">{{ $secret }}</p>
 </div>

 <div class="space-y-5">
 @if ($enabled)
 <div class="rounded-[var(--radius-card)] border border-leaf bg-leaf-light p-4 text-sm text-[color:var(--success)]" data-ui="two-factor-enabled-state">
 Two-factor authentication is enabled for this account.
 </div>

 <form method="POST" action="{{ route('settings.two-factor.disable') }}" class="space-y-4" data-ui="two-factor-disable-form">
 @csrf
 @method('DELETE')

 <x-ui.input
  id="current_password_disable"
  name="current_password"
  type="password"
  label="Current password"
  autocomplete="current-password"
  required
 />

 <x-ui.button type="submit" variant="danger" class="min-h-11 sm:min-w-44">
 Disable two-factor
 </x-ui.button>
 </form>
 @else
 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white/80 p-4 text-sm leading-6 text-fur" data-ui="two-factor-disabled-state">
 Scan the code in your authenticator app, then confirm with your current password.
 </div>

 <form method="POST" action="{{ route('settings.two-factor.enable') }}" class="space-y-4" data-ui="two-factor-enable-form">
 @csrf

 <x-ui.input
  id="current_password"
  name="current_password"
  type="password"
  label="Current password"
  autocomplete="current-password"
  required
 />

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-44">
 Enable two-factor
 </x-ui.button>
 </form>
 @endif
 </div>
 </div>
 </div>
</x-settings-layout>
