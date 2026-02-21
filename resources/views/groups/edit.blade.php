<x-app-layout>
    @php
        $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
        $privacyValue = old('privacy', $selectedPrivacy ?? 'public');
        $speciesValue = old('species', data_get($group, 'species', 'all_pets'));

        $privacyOptions = [
            'public' => 'Anyone can discover and join instantly.',
            'private' => 'Visible in search, but new members need approval.',
            'secret' => 'Hidden from discovery and joinable by invite only.',
        ];

        $speciesOptions = [
            'all_pets' => 'All Pets',
            'dogs' => 'Dogs',
            'cats' => 'Cats',
            'birds' => 'Birds',
            'small_pets' => 'Small Pets',
            'reptiles' => 'Reptiles',
            'aquatic' => 'Aquatic',
            'mixed' => 'Mixed',
        ];
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Manage Community</p>
                <h2 class="shell-title text-xl leading-tight">Edit Group</h2>
            </div>
            <a href="{{ route('groups.show', $groupRouteKey) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Group</a>
        </div>
    </x-slot>

    <section
        class="shell-card p-5 sm:p-6"
        x-data="{
            name: @js(old('name', $group->name ?? '')),
            description: @js(old('description', $group->description ?? '')),
            rules: @js(old('rules', $group->rules ?? '')),
            avatarSrc: @js(data_get($group, 'avatar_url', data_get($group, 'profile_photo_url', ''))),
            coverSrc: @js(data_get($group, 'cover_photo_url', data_get($group, 'cover_image_path', ''))),
            avatarObjectUrl: null,
            coverObjectUrl: null,
            setAvatarPreview(event) {
                const file = event?.target?.files?.[0];

                if (this.avatarObjectUrl) {
                    URL.revokeObjectURL(this.avatarObjectUrl);
                    this.avatarObjectUrl = null;
                }

                if (!file) {
                    return;
                }

                this.avatarObjectUrl = URL.createObjectURL(file);
                this.avatarSrc = this.avatarObjectUrl;
            },
            setCoverPreview(event) {
                const file = event?.target?.files?.[0];

                if (this.coverObjectUrl) {
                    URL.revokeObjectURL(this.coverObjectUrl);
                    this.coverObjectUrl = null;
                }

                if (!file) {
                    return;
                }

                this.coverObjectUrl = URL.createObjectURL(file);
                this.coverSrc = this.coverObjectUrl;
            }
        }"
    >
        <form method="POST" action="{{ route('groups.update', $groupRouteKey) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm font-semibold">Group Name</label>
                    <x-text-input id="name" name="name" type="text" class="block w-full" x-model="name" :value="old('name', $group->name ?? '')" maxlength="160" required />
                    <div class="mt-1 flex justify-end text-xs shell-text-muted"><span x-text="`${name.length}/160`"></span></div>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <label for="species" class="mb-1 block text-sm font-semibold">Species Focus</label>
                    <select id="species" name="species" class="form-select">
                        @foreach ($speciesOptions as $value => $label)
                            <option value="{{ $value }}" @selected($speciesValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('species')" class="mt-2" />
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold">Group Type</p>
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($privacyOptions as $value => $description)
                        <label class="cursor-pointer rounded-xl border p-3 transition-colors {{ $privacyValue === $value ? 'border-emerald-300 bg-emerald-50' : 'border-[color:var(--ui-border)] hover:bg-[color:var(--ui-surface-muted)]' }}">
                            <div class="flex items-start gap-2">
                                <input type="radio" name="privacy" value="{{ $value }}" class="mt-1" @checked($privacyValue === $value)>
                                <span>
                                    <span class="block text-sm font-semibold" style="color: var(--ui-text);">{{ \Illuminate\Support\Str::headline($value) }}</span>
                                    <span class="mt-0.5 block text-xs shell-text-muted">{{ $description }}</span>
                                </span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('privacy')" class="mt-2" />
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between gap-3">
                    <label for="description" class="block text-sm font-semibold">Description</label>
                    <span class="text-xs shell-text-muted" x-text="`${description.length}/5000`"></span>
                </div>
                <textarea id="description" name="description" rows="5" class="form-textarea" x-model="description">{{ old('description', $group->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between gap-3">
                    <label for="rules" class="block text-sm font-semibold">Group Rules</label>
                    <span class="text-xs shell-text-muted" x-text="`${rules.length}/5000`"></span>
                </div>
                <textarea id="rules" name="rules" rows="4" class="form-textarea" x-model="rules">{{ old('rules', $group->rules ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('rules')" class="mt-2" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="space-y-3">
                    <label for="avatar" class="block text-sm font-semibold">Avatar</label>
                    <div class="flex items-center gap-3 rounded-xl border border-[color:var(--ui-border)] p-3">
                        <div class="h-16 w-16 overflow-hidden rounded-full border border-[color:var(--ui-border)] bg-[color:var(--ui-surface-muted)]">
                            <img x-show="avatarSrc" x-cloak :src="avatarSrc" alt="Group avatar preview" class="h-full w-full object-cover">
                            <div x-show="!avatarSrc" class="flex h-full items-center justify-center text-lg">🐾</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <input id="avatar" name="avatar" type="file" accept="image/*" class="form-input" @change="setAvatarPreview($event)">
                            <p class="mt-1 text-xs shell-text-muted">Square image recommended.</p>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
                </div>

                <div class="space-y-3">
                    <label for="cover" class="block text-sm font-semibold">Cover</label>
                    <div class="h-28 overflow-hidden rounded-xl border border-[color:var(--ui-border)] bg-[color:var(--ui-surface-muted)]">
                        <img x-show="coverSrc" x-cloak :src="coverSrc" alt="Group cover preview" class="h-full w-full object-cover">
                        <div x-show="!coverSrc" class="flex h-full items-center justify-center text-sm shell-text-muted">No cover selected</div>
                    </div>
                    <input id="cover" name="cover" type="file" accept="image/*" class="form-input" @change="setCoverPreview($event)">
                    <x-input-error :messages="$errors->get('cover')" class="mt-2" />
                </div>
            </div>

            <div>
                <label for="cover_image_path" class="mb-1 block text-sm font-semibold">Cover URL (optional fallback)</label>
                <x-text-input id="cover_image_path" name="cover_image_path" type="url" class="block w-full" :value="old('cover_image_path', $group->cover_image_path ?? '')" placeholder="https://example.com/cover.jpg" />
                <x-input-error :messages="$errors->get('cover_image_path')" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                @if (! empty($canDelete))
                    <form method="POST" action="{{ route('groups.destroy', $groupRouteKey) }}" onsubmit="return confirm('Delete this group?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-base btn-danger">Delete Group</button>
                    </form>
                @else
                    <span></span>
                @endif

                <div class="flex items-center gap-2">
                    <a href="{{ route('groups.show', $groupRouteKey) }}" class="btn-base btn-ghost">Cancel</a>
                    <button type="submit" class="btn-base btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </section>
</x-app-layout>
