@section('title', 'Profile')

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Profile & Account</h1>
            <p class="mt-1 text-sm shell-text-muted">Manage your public profile, password, and account safety settings.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="shell-card p-6 dark:border-slate-700/60 dark:bg-slate-900/40">
            <h2 class="shell-title text-lg">Public Profile</h2>
            <p class="mt-1 text-sm shell-text-muted">Edit display name, username, bio, avatar, and cover with live preview.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('settings.profile.edit') }}" class="btn-base btn-primary" aria-label="Open profile settings">
                    Open Profile Settings
                </a>
                <a href="{{ route('settings.account.edit') }}" class="btn-base btn-ghost" aria-label="Open account settings">
                    Open Account Settings
                </a>
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
