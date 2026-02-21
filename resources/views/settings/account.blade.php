@section('title', 'Account Settings')

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Account Settings</h1>
            <p class="mt-1 text-sm shell-text-muted">Manage privacy, blocked users, security, and the account danger zone.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="shell-card p-6 dark:border-slate-700/60 dark:bg-slate-900/40">
            <h2 class="shell-title text-lg">Privacy</h2>
            <p class="mt-1 text-sm shell-text-muted">Private accounts are visible only to followers you approve.</p>

            <form method="POST" action="{{ route('settings.account.privacy') }}" class="mt-4 space-y-4" aria-label="Account privacy settings">
                @csrf
                @method('PATCH')

                <input type="hidden" name="is_private" value="0">

                <label for="is_private" class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] p-3 dark:border-slate-700/60 dark:bg-slate-900/30">
                    <div>
                        <p class="text-sm font-semibold">Private account</p>
                        <p class="text-xs shell-text-muted">Only approved followers can view your profile content.</p>
                    </div>

                    <span class="relative inline-flex h-7 w-12 shrink-0 items-center">
                        <input
                            id="is_private"
                            type="checkbox"
                            name="is_private"
                            value="1"
                            @checked(old('is_private', $user->is_private))
                            class="peer sr-only"
                            aria-label="Toggle private account"
                        >
                        <span class="h-7 w-12 rounded-full border border-[var(--ui-border)] bg-[color:var(--ui-surface-muted)] transition peer-checked:border-emerald-500 peer-checked:bg-emerald-500/20 dark:border-slate-700/60"></span>
                        <span class="absolute left-1 h-5 w-5 rounded-full bg-white shadow transition peer-checked:left-6 dark:bg-slate-200"></span>
                    </span>
                </label>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-base btn-primary">Save Privacy</button>
                    @if (session('status') === 'privacy-updated')
                        <p class="text-sm shell-text-muted">Privacy setting updated.</p>
                    @endif
                </div>
            </form>

            <x-input-error :messages="$errors->get('is_private')" class="mt-2" />
        </section>

        <section class="shell-card p-6 dark:border-slate-700/60 dark:bg-slate-900/40" x-data="{ unblocking: null, notice: '' }">
            <h2 class="shell-title text-lg">Blocked Users</h2>
            <p class="mt-1 text-sm shell-text-muted">Blocked users cannot follow you or interact with your profile.</p>

            <div class="mt-4 space-y-3">
                @forelse ($blockedUsers as $blockedUser)
                    @php
                        $canUnblock = filled($blockedUser->username);
                        $unblockUrl = $canUnblock ? route('users.unblock', ['user' => $blockedUser]) : null;
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3 dark:border-slate-700/60 dark:bg-slate-900/30">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-avatar :src="$blockedUser->getFirstMediaUrl('avatar')" :name="$blockedUser->name" size="md" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $blockedUser->name }}</p>
                                <p class="truncate text-xs shell-text-muted">@{{ $blockedUser->username }}</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn-base btn-ghost px-3 py-2 text-xs"
                            :disabled="!{{ $canUnblock ? 'true' : 'false' }} || unblocking === {{ $blockedUser->id }}"
                            aria-label="Unblock {{ $blockedUser->name }}"
                            @click="(async () => {
                                if (!{{ $canUnblock ? 'true' : 'false' }}) {
                                    return;
                                }
                                unblocking = {{ $blockedUser->id }};
                                notice = '';

                                try {
                                    const response = await fetch('{{ $unblockUrl }}', {
                                        method: 'DELETE',
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        },
                                    });

                                    const payload = await response.json();

                                    if (payload.success) {
                                        location.reload();
                                        return;
                                    }

                                    notice = payload.message || 'Unable to unblock this user.';
                                } catch (error) {
                                    notice = 'Unable to unblock this user.';
                                } finally {
                                    unblocking = null;
                                }
                            })()"
                        >
                            <span x-text="unblocking === {{ $blockedUser->id }} ? 'Updating...' : '{{ $canUnblock ? 'Unblock' : 'Unavailable' }}'"></span>
                        </button>
                    </div>
                @empty
                    <x-empty-state
                        icon="🛡️"
                        title="No blocked users"
                        description="When you block someone, they will appear here."
                        class="mt-4"
                    />
                @endforelse
            </div>

            <p class="mt-3 text-sm shell-text-muted" x-show="notice" x-text="notice"></p>

            <div class="mt-4">
                {{ $blockedUsers->links() }}
            </div>
        </section>

        <section class="shell-card p-6 dark:border-slate-700/60 dark:bg-slate-900/40">
            @include('profile.partials.update-password-form')
        </section>

        <section class="shell-card p-6 dark:border-slate-700/60 dark:bg-slate-900/40">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
