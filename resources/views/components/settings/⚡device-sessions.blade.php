<?php

use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\DeviceSessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

new class extends Component
{
    public bool $confirmingLogoutOtherDevices = false;

    public string $password = '';

    public ?string $statusMessage = null;

    public function openLogoutOtherDevicesModal(): void
    {
        $this->resetErrorBag('password');
        $this->password = '';
        $this->confirmingLogoutOtherDevices = true;
    }

    public function closeLogoutOtherDevicesModal(): void
    {
        $this->resetErrorBag('password');
        $this->password = '';
        $this->confirmingLogoutOtherDevices = false;
    }

    public function destroySession(string $sessionId, DeviceSessionService $sessions, AuthAuditLogger $auditLogger): void
    {
        $user = $this->currentUser();
        $deleted = $sessions->destroySession($user, $sessionId, session()->getId());

        if ($deleted > 0) {
            $auditLogger->record($user, 'other_sessions_logged_out', request(), [
                'deleted_sessions' => $deleted,
                'session_id_hash' => hash('sha256', $sessionId),
            ]);
        }

        $this->statusMessage = $deleted > 0
            ? 'That device has been logged out.'
            : 'That session could not be logged out.';
    }

    public function logoutOtherDevices(DeviceSessionService $sessions, AuthAuditLogger $auditLogger): void
    {
        $validated = $this->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Confirm your password before logging out other devices.',
        ]);

        $user = $this->currentUser();
        $password = (string) $validated['password'];

        if (! Hash::check($password, (string) $user->password)) {
            $this->addError('password', 'The provided password does not match your current password.');

            return;
        }

        Auth::logoutOtherDevices($password);

        $deleted = $sessions->destroyOtherSessions($user, session()->getId());

        $user->forceFill([
            'remember_token' => null,
        ])->saveQuietly();

        $auditLogger->record($user, 'other_sessions_logged_out', request(), [
            'deleted_sessions' => $deleted,
            'remember_token_cleared' => true,
        ]);

        $this->password = '';
        $this->confirmingLogoutOtherDevices = false;
        $this->statusMessage = 'You have been logged out of all other devices.';
    }

    /**
     * @return list<array{id: string, ip_address: string|null, user_agent: string|null, device_type: string, browser_name: string, browser_version: string|null, os_name: string, os_version: string|null, browser_label: string, os_label: string, summary: string, country_code: string|null, country: string, city: string|null, location_label: string, is_current: bool, last_activity: int}>
     */
    public function sessions(): array
    {
        return app(DeviceSessionService::class)->activeSessions($this->currentUser(), session()->getId());
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div class="mt-10 border-t border-whisker/30 pt-6" data-ui="device-sessions-list">
 @if ($statusMessage)
 <x-ui.alert type="success" class="mb-4" data-ui="device-sessions-status">
 {{ $statusMessage }}
 </x-ui.alert>
 @endif

 <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
 <div>
 <h3 class="text-lg font-semibold text-bark">Active sessions</h3>
 <p class="mt-1 max-w-2xl text-sm leading-6 shell-text-muted">Review signed-in browsers and log out devices you no longer recognize.</p>
 </div>
 <x-ui.button type="button" variant="secondary" class="min-h-11 justify-center sm:min-w-56" wire:click="openLogoutOtherDevicesModal">
 Log out of all other devices
 </x-ui.button>
 </div>

 <ul class="mt-5 divide-y divide-whisker/30 rounded-[var(--radius-card)] border border-whisker/40" aria-label="Active account sessions">
 @forelse ($this->sessions() as $session)
 <li class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center" data-ui="device-session-row" wire:key="device-session-{{ $session['id'] }}">
 <div class="min-w-0">
 <div class="flex flex-wrap items-center gap-3">
 <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-soft)] bg-cream text-paw-dark" aria-hidden="true">
 @switch($session['device_type'])
 @case('mobile')
 <x-heroicon-o-device-phone-mobile class="h-5 w-5"/>
 @break
 @case('tablet')
 <x-heroicon-o-device-tablet class="h-5 w-5"/>
 @break
 @default
 <x-heroicon-o-computer-desktop class="h-5 w-5"/>
 @endswitch
 </span>
 <div class="min-w-0">
 <div class="flex flex-wrap items-center gap-2">
 <p class="font-semibold text-bark">{{ $session['summary'] }}</p>
 @if ($session['is_current'])
 <span class="rounded-full bg-leaf-light px-2 py-0.5 text-xs font-semibold text-[color:var(--success)]">This device</span>
 @endif
 </div>
 <p class="mt-1 text-sm text-fur">{{ $session['location_label'] }}</p>
 <p class="mt-0.5 text-xs text-fur">IP {{ $session['ip_address'] ?? 'Unknown' }}</p>
 </div>
 </div>
 </div>

 <div class="flex flex-col gap-3 sm:items-end">
 @php($lastActivity = Carbon::createFromTimestamp($session['last_activity']))
 <time datetime="{{ $lastActivity->toIso8601String() }}" class="text-sm text-fur">
 Active {{ $lastActivity->diffForHumans() }}
 </time>

 @unless ($session['is_current'])
 <div class="relative" x-data="{ confirming: false }">
 <x-ui.button type="button" variant="ghost" size="sm" class="min-h-10" x-on:click="confirming = ! confirming" x-bind:aria-expanded="confirming.toString()">
 Log out
 </x-ui.button>

 <div
  x-show="confirming"
  x-cloak
  x-transition
  @click.outside="confirming = false"
  class="absolute right-0 z-20 mt-2 w-64 rounded-[var(--radius-card)] border border-whisker/40 bg-[color:var(--surface-modal)] p-3 text-left shadow-soft"
  role="dialog"
  aria-label="Confirm device logout"
 >
 <p class="text-sm font-semibold text-bark">Log out of this device?</p>
 <div class="mt-3 flex items-center justify-end gap-2">
 <button type="button" class="btn-base btn-ghost h-[var(--control-height-sm)] px-3 text-xs" x-on:click="confirming = false">Cancel</button>
 <button
  type="button"
  class="btn-base btn-danger h-[var(--control-height-sm)] px-3 text-xs"
  x-on:click="$wire.destroySession(@js($session['id'])); confirming = false"
  wire:loading.class="pointer-events-none opacity-60"
  wire:target="destroySession"
 >
 Confirm
 </button>
 </div>
 </div>
 </div>
 @endunless
 </div>
 </li>
 @empty
 <li class="p-4 text-sm text-fur">No database-backed sessions are currently recorded.</li>
 @endforelse
 </ul>

 @if ($confirmingLogoutOtherDevices)
 <div
  class="fixed inset-0 z-50 overflow-y-auto"
  role="dialog"
  aria-modal="true"
  aria-labelledby="logout-other-devices-title"
  x-data
  x-on:keydown.escape.window="$wire.closeLogoutOtherDevicesModal()"
 >
 <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:items-center sm:p-0">
 <button type="button" class="fixed inset-0 bg-bark/40" wire:click="closeLogoutOtherDevicesModal" aria-label="Close logout confirmation"></button>
 <div class="relative inline-block w-full max-w-md transform overflow-hidden rounded-[var(--radius-card)] bg-[color:var(--surface-modal)] text-left align-bottom transition-all sm:align-middle">
 <div class="border-b border-whisker/40 px-6 py-4">
 <h3 id="logout-other-devices-title" class="text-lg font-semibold font-display text-bark">Log out of all other devices</h3>
 <p class="mt-1 text-sm text-fur">Enter your current password to invalidate other browser sessions and persistent logins.</p>
 </div>
 <form wire:submit="logoutOtherDevices">
 <div class="px-6 py-5">
 <x-ui.input
  id="logout_other_devices_password"
  name="password"
  type="password"
  label="Current password"
  autocomplete="current-password"
  required
  wire:model="password"
 />
 </div>
 <div class="flex flex-col-reverse gap-3 border-t border-whisker/40 bg-cream/50 px-6 py-4 sm:flex-row sm:justify-end">
 <x-ui.button type="button" variant="ghost" class="min-h-11 justify-center" wire:click="closeLogoutOtherDevicesModal">
 Cancel
 </x-ui.button>
 <x-ui.button type="submit" variant="danger" class="min-h-11 justify-center" wire:loading.attr="disabled" wire:target="logoutOtherDevices">
 <span wire:loading.remove wire:target="logoutOtherDevices">Confirm logout</span>
 <span wire:loading.flex wire:target="logoutOtherDevices" class="items-center gap-2">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 Logging out...
 </span>
 </x-ui.button>
 </div>
 </form>
 </div>
 </div>
 </div>
 @endif
</div>
