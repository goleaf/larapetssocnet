<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Account Settings</h1>
            <p class="mt-1 text-sm shell-text-muted">Manage privacy, blocked users, password, and account deletion.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="shell-card p-6">
            <h2 class="shell-title text-lg">Privacy</h2>
            <p class="mt-1 text-sm shell-text-muted">Private accounts are visible only to followers.</p>

            <form method="POST" action="{{ route('settings.account.privacy') }}" class="mt-4 flex flex-wrap items-center gap-3">
                @csrf
                @method('PATCH')

                <label class="inline-flex items-center gap-2">
                    <input
                        type="hidden"
                        name="is_private"
                        value="0"
                    >
                    <input
                        type="checkbox"
                        name="is_private"
                        value="1"
                        @checked(old('is_private', $user->is_private))
                        class="h-4 w-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                    >
                    <span class="text-sm font-semibold">Make my account private</span>
                </label>

                <button type="submit" class="btn-base btn-primary">Update Privacy</button>
            </form>

            <x-input-error :messages="$errors->get('is_private')" class="mt-2" />
            @if (session('status') === 'privacy-updated')
                <p class="mt-2 text-sm shell-text-muted">Privacy setting updated.</p>
            @endif
        </section>

        <section class="shell-card p-6" x-data="{ unblocking: null }">
            <h2 class="shell-title text-lg">Blocked Users</h2>
            <p class="mt-1 text-sm shell-text-muted">Blocked users cannot follow or interact with your profile.</p>

            <div class="mt-4 space-y-3">
                @forelse ($blockedUsers as $blockedUser)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $blockedUser->name }}</p>
                            <p class="truncate text-xs shell-text-muted">@{{ $blockedUser->username }}</p>
                        </div>
                        <button
                            type="button"
                            class="btn-base btn-ghost px-3 py-2 text-xs"
                            :disabled="unblocking === {{ $blockedUser->id }}"
                            @click="(async () => {
                                unblocking = {{ $blockedUser->id }};
                                const response = await fetch('{{ route('users.unblock', ['user' => $blockedUser]) }}', {
                                    method: 'DELETE',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                    }
                                });
                                const payload = await response.json();
                                if (payload.success) {
                                    location.reload();
                                }
                                unblocking = null;
                            })()"
                        >
                            Unblock
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

            <div class="mt-4">
                {{ $blockedUsers->links() }}
            </div>
        </section>

        <section class="shell-card p-6">
            @include('profile.partials.update-password-form')
        </section>

        <section class="shell-card p-6">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
