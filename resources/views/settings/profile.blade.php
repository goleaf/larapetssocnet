@php
    $avatarUrl = $user->getFirstMediaUrl('avatar');
    $coverUrl = $user->getFirstMediaUrl('cover');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Profile Settings</h1>
            <p class="mt-1 text-sm shell-text-muted">Update your public profile fields and images.</p>
        </div>
    </x-slot>

    <div class="shell-card p-6 sm:p-8">
        <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm font-semibold">Name</label>
                    <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $user->name) }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <label for="username" class="mb-1 block text-sm font-semibold">Username</label>
                    <input id="username" name="username" type="text" class="form-input" value="{{ old('username', $user->username) }}" />
                    <p class="mt-1 text-xs shell-text-muted">Use letters, numbers, dots, and underscores.</p>
                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="email" class="mb-1 block text-sm font-semibold">Email</label>
                    <input id="email" name="email" type="email" class="form-input" value="{{ old('email', $user->email) }}" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <label for="website" class="mb-1 block text-sm font-semibold">Website</label>
                    <input id="website" name="website" type="url" class="form-input" value="{{ old('website', $user->website) }}" placeholder="https://example.com" />
                    <x-input-error :messages="$errors->get('website')" class="mt-2" />
                </div>
            </div>

            <div>
                <label for="location" class="mb-1 block text-sm font-semibold">Location</label>
                <input id="location" name="location" type="text" class="form-input" value="{{ old('location', $user->location) }}" />
                <x-input-error :messages="$errors->get('location')" class="mt-2" />
            </div>

            <div>
                <label for="bio" class="mb-1 block text-sm font-semibold">Bio</label>
                <textarea id="bio" name="bio" rows="4" class="form-textarea">{{ old('bio', $user->bio) }}</textarea>
                <x-input-error :messages="$errors->get('bio')" class="mt-2" />
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-3">
                    <label for="avatar" class="block text-sm font-semibold">Avatar</label>
                    <div class="flex items-center gap-3">
                        <x-avatar :src="$avatarUrl" :name="$user->name" size="xl" />
                        <input id="avatar" name="avatar" type="file" accept="image/*" class="form-input" />
                    </div>
                    <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
                </div>

                <div class="space-y-3">
                    <label for="cover" class="block text-sm font-semibold">Cover</label>
                    @if ($coverUrl)
                        <img src="{{ $coverUrl }}" alt="Cover image" class="h-24 w-full rounded-xl object-cover" />
                    @else
                        <div class="flex h-24 items-center justify-center rounded-xl border border-dashed border-[var(--ui-border)] text-sm shell-text-muted">
                            No cover image yet
                        </div>
                    @endif
                    <input id="cover" name="cover" type="file" accept="image/*" class="form-input" />
                    <x-input-error :messages="$errors->get('cover')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="btn-base btn-primary">Save Changes</button>

                @if (session('status') === 'profile-updated')
                    <p class="text-sm shell-text-muted">Saved.</p>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
