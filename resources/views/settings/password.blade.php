<x-settings-layout>
 <div class="space-y-6" data-ui="settings-password-page">
 <div class="space-y-2" data-ui="settings-page-header">
 <p class="chip min-h-8">Security</p>
 <h2 class="shell-title text-2xl">Password</h2>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">Ensure your account is using a long, random password to stay secure.
 </p>
 </div>

 <form action="{{ route('settings.password.update') }}" method="POST" class="space-y-6" data-ui="settings-password-form">
 @csrf
 @method('PUT')

 <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-6 sm:gap-x-6">
 <div class="sm:col-span-4">
 <x-ui.input id="current_password" name="current_password" type="password" label="Current Password"
 autocomplete="current-password" required/>
 </div>

 <div class="sm:col-span-4">
 <x-ui.input id="password" name="password" type="password" label="New Password" autocomplete="new-password" required/>
 </div>

 <div class="sm:col-span-4">
 <x-ui.input id="password_confirmation" name="password_confirmation" type="password" label="Confirm New Password"
 autocomplete="new-password" required/>
 </div>
 </div>

 <div class="flex justify-start border-t border-whisker/30 pt-5">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-36">Save Password</x-ui.button>
 </div>
 </form>

 <div class="mt-10 border-t border-whisker/30 pt-6">
 <h3 class="text-lg font-semibold text-bark">Security Information</h3>
 <dl class="mt-4 divide-y divide-whisker/30">
 <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4">
 <dt class="text-sm font-medium text-fur">Last password change</dt>
 <dd class="mt-1 text-sm text-bark sm:col-span-2 sm:mt-0">
 {{ auth()->user()->password_changed_at ? auth()->user()->password_changed_at->diffForHumans() :'Never'}}
 </dd>
 </div>
 <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4">
 <dt class="text-sm font-medium text-fur">Two-factor authentication</dt>
 <dd class="mt-1 text-sm text-bark sm:col-span-2 sm:mt-0">
 <a href="{{ route('settings.two-factor') }}" class="font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Manage authenticator codes
 </a>
 </dd>
 </div>
 </dl>
 </div>

 <div class="mt-10 border-t border-whisker/30 pt-6" data-ui="device-sessions-list">
 <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
 <div>
 <h3 class="text-lg font-semibold text-bark">Active sessions</h3>
 <p class="mt-1 max-w-2xl text-sm leading-6 shell-text-muted">Review signed-in browsers and log out other devices.</p>
 </div>
 <form action="{{ route('settings.sessions.destroy-other') }}" method="POST" class="flex flex-col gap-2 sm:min-w-72">
 @csrf
 @method('DELETE')
 <x-ui.input id="session_password" name="password" type="password" label="Current password" autocomplete="current-password" required/>
 <x-ui.button type="submit" variant="secondary" class="min-h-11">Log out other sessions</x-ui.button>
 </form>
 </div>

 <div class="mt-5 divide-y divide-whisker/30 rounded-[var(--radius-card)] border border-whisker/40">
 @forelse ($sessions ?? collect() as $session)
 <div class="grid gap-2 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center" data-ui="device-session-row">
 <div class="min-w-0">
 <div class="flex flex-wrap items-center gap-2">
 <p class="font-semibold text-bark">{{ $session['browser'] }} on {{ $session['platform'] }}</p>
 @if ($session['is_current'])
 <span class="rounded-full bg-leaf-light px-2 py-0.5 text-xs font-semibold text-[color:var(--success)]">Current</span>
 @endif
 </div>
 <p class="mt-1 text-sm text-fur">{{ $session['ip_address'] ?? 'Unknown IP' }}</p>
 </div>
 <p class="text-sm text-fur">
 {{ \Illuminate\Support\Carbon::createFromTimestamp($session['last_activity'])->diffForHumans() }}
 </p>
 </div>
 @empty
 <p class="p-4 text-sm text-fur">No database-backed sessions are currently recorded.</p>
 @endforelse
 </div>
 </div>
 </div>
</x-settings-layout>
