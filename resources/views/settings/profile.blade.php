@php
    $avatarUrl = $user->getFirstMediaUrl('avatar');
    $coverUrl = $user->getFirstMediaUrl('cover');
    $currentLocation = $user->location ?? $user->city;
@endphp

@section('title', 'Profile Settings')

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Profile Settings</h1>
            <p class="mt-1 text-sm shell-text-muted">Update your public details with a live profile preview.</p>
        </div>
    </x-slot>

    <div
        class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]"
        x-data="profileEditorPreview({
            name: @js(old('name', $user->name)),
            username: @js(old('username', $user->username)),
            bio: @js(old('bio', $user->bio)),
            location: @js(old('location', $currentLocation)),
            website: @js(old('website', $user->website)),
            avatarUrl: @js($avatarUrl),
            coverUrl: @js($coverUrl)
        })"
    >
        <section class="shell-card p-6 sm:p-8 dark:border-slate-700/60 dark:bg-slate-900/40">
            <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data" class="space-y-6" aria-label="Edit public profile">
                @csrf
                @method('PATCH')

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-semibold">Name</label>
                        <input id="name" name="name" type="text" class="form-input" x-model="name" value="{{ old('name', $user->name) }}" required aria-label="Your display name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="username" class="mb-1 block text-sm font-semibold">Username</label>
                        @if (! $user->canChangeUsername())
                            <div class="mt-1 flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                                <span class="text-gray-400">@</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $user->username }}</span>
                                <span class="ml-auto rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                    Can change in {{ $user->daysUntilUsernameChange() }} days
                                </span>
                            </div>
                            <input type="hidden" name="username" value="{{ $user->username }}">
                            <p class="mt-1 text-xs shell-text-muted">Usernames can only be changed once every 30 days.</p>
                        @else
                            <div
                                class="relative mt-1"
                                x-data="{ original: @js($user->username), val: @js(old('username', $user->username)), status: 'original', checking: false, message: '', get isChanged(){ return this.val !== this.original; }, async check(){ if(!this.isChanged){ this.status='original'; return; } if(this.val.length < 3){ this.status='short'; return; } this.checking=true; const res=await fetch('{{ route('api.username.available') }}?username='+encodeURIComponent(this.val)); const data=await res.json(); this.status = data.available ? 'ok' : 'taken'; this.message = data.message ?? ''; this.checking=false; } }"
                            >
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">@</span>
                                <input id="username" name="username" type="text" class="form-input block w-full pl-7" x-model="val" value="{{ old('username', $user->username) }}" maxlength="30" @input.debounce.500ms="check()" aria-label="Your username" />
                                <div class="mt-1 h-5 text-sm">
                                    <span x-show="checking" class="text-gray-400">Checking availability...</span>
                                    <span x-show="status === 'ok'" class="text-emerald-600">✓ Available</span>
                                    <span x-show="status === 'taken'" class="text-red-600">✗ Already taken</span>
                                    <span x-show="status === 'original'" class="text-gray-400">Your current username</span>
                                </div>
                                <div x-show="status === 'ok'" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                    Changing your username keeps a redirect from your old URL for 90 days.
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        @endif
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="email" class="mb-1 block text-sm font-semibold">Email</label>
                        <input id="email" name="email" type="email" class="form-input" value="{{ old('email', $user->email) }}" required aria-label="Your email address" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <label for="website" class="mb-1 block text-sm font-semibold">Website</label>
                        <input id="website" name="website" type="url" class="form-input" x-model="website" value="{{ old('website', $user->website) }}" placeholder="https://example.com" aria-label="Website URL" />
                        <x-input-error :messages="$errors->get('website')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <label for="location" class="mb-1 block text-sm font-semibold">Location</label>
                    <input id="location" name="location" type="text" class="form-input" x-model="location" value="{{ old('location', $currentLocation) }}" aria-label="Location" />
                    <x-input-error :messages="$errors->get('location')" class="mt-2" />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <label for="bio" class="block text-sm font-semibold">Bio</label>
                        <span class="text-xs shell-text-muted" x-text="`${bio.length}/1000`"></span>
                    </div>
                    <textarea id="bio" name="bio" rows="4" class="form-textarea" x-model="bio" aria-label="Bio">{{ old('bio', $user->bio) }}</textarea>
                    <x-input-error :messages="$errors->get('bio')" class="mt-2" />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-3">
                        <label for="avatar" class="block text-sm font-semibold">Avatar</label>
                        <div class="flex items-center gap-3 rounded-xl border border-[var(--ui-border)] p-3 dark:border-slate-700/60 dark:bg-slate-900/30">
                            <x-avatar :src="$avatarUrl" :name="$user->name" size="xl" />
                            <div class="flex-1">
                                <input id="avatar" name="avatar" type="file" accept="image/*" class="form-input" @change="setAvatarPreview($event)" aria-label="Upload avatar image" />
                                <p class="mt-1 text-xs shell-text-muted">Square image works best.</p>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
                    </div>

                    <div class="space-y-3">
                        <label for="cover" class="block text-sm font-semibold">Cover</label>
                        <div class="h-28 overflow-hidden rounded-xl border border-[var(--ui-border)] dark:border-slate-700/60">
                            <img
                                x-show="coverSrc"
                                x-cloak
                                :src="coverSrc"
                                alt="Cover preview"
                                class="h-full w-full object-cover"
                            >
                            <div
                                x-show="!coverSrc"
                                class="flex h-full items-center justify-center text-sm shell-text-muted"
                                style="background: color-mix(in srgb, var(--ui-primary) 10%, var(--ui-surface) 90%);"
                            >
                                No cover image selected
                            </div>
                        </div>
                        <input id="cover" name="cover" type="file" accept="image/*" class="form-input" @change="setCoverPreview($event)" aria-label="Upload cover image" />
                        <x-input-error :messages="$errors->get('cover')" class="mt-2" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-base btn-primary">Save Changes</button>
                    <a href="{{ route('settings.account.edit') }}" class="btn-base btn-ghost" aria-label="Open account settings">Account Settings</a>

                    @if (session('status') === 'profile-updated')
                        <p class="text-sm shell-text-muted">Saved.</p>
                    @endif
                </div>
            </form>
        </section>

        <aside class="shell-card p-4 dark:border-slate-700/60 dark:bg-slate-900/40 lg:sticky lg:top-24 lg:self-start">
            <p class="shell-kicker">Live Preview</p>

            <div class="mt-3 overflow-hidden rounded-2xl border border-[var(--ui-border)] dark:border-slate-700/60">
                <div class="relative h-28 w-full">
                    <img x-show="coverSrc" x-cloak :src="coverSrc" alt="Profile cover preview" class="h-full w-full object-cover">
                    <div
                        x-show="!coverSrc"
                        class="h-full w-full"
                        style="background: linear-gradient(120deg, color-mix(in srgb, var(--ui-primary) 24%, var(--ui-surface) 76%), color-mix(in srgb, var(--ui-accent) 22%, var(--ui-surface) 78%));"
                    ></div>
                </div>

                <div class="relative p-4">
                    <div class="-mt-10 inline-flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border-4 border-[var(--ui-surface)] bg-[color:var(--ui-surface-muted)] text-xl font-bold dark:border-slate-900/80">
                        <img x-show="avatarSrc" x-cloak :src="avatarSrc" :alt="`${displayName} avatar preview`" class="h-full w-full object-cover">
                        <span x-show="!avatarSrc" x-text="initials"></span>
                    </div>

                    <p class="mt-3 shell-title text-lg" x-text="displayName"></p>
                    <p class="text-sm shell-text-muted" x-text="displayUsername"></p>

                    <p class="mt-3 text-sm" x-show="bio" x-text="bio"></p>
                    <p class="mt-3 text-sm shell-text-muted" x-show="location" x-text="`📍 ${location}`"></p>
                    <a
                        x-show="safeWebsite"
                        :href="safeWebsite"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 inline-flex text-sm font-semibold hover:underline"
                        style="color: var(--ui-primary);"
                        x-text="safeWebsite"
                    ></a>
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
