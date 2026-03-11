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
        <x-ui.page-header title="Edit Group" subtitle="Manage Community">
            <x-slot:action>
                <x-ui.button href="{{ route('groups.show', $groupRouteKey) }}" variant="ghost" size="sm">Back to
                    Group</x-ui.button>
            </x-slot:action>
        </x-ui.page-header>
    </x-slot>

    <x-ui.card padding="lg">
        <div x-data="{
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
            }">
            <form id="edit-group-form" method="POST" action="{{ route('groups.update', $groupRouteKey) }}"
                enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <x-ui.input id="name" name="name" type="text" label="Group Name" x-model="name"
                            :value="old('name', $group->name ?? '')" maxlength="160" required  />
                        <div class="mt-1 flex justify-end text-xs text-fur"><span x-text="`${name.length}/160`"></span>
                        </div>
                    </div>

                    <div>
                        <x-ui.select id="species" name="species" label="Species Focus" :options="$speciesOptions"
                            :value="$speciesValue"  />
                    </div>
                </div>

                <div>
                    <x-ui.label class="mb-2">Group Type</x-ui.label>
                    <div class="grid gap-3 md:grid-cols-3">
                        @foreach ($privacyOptions as $value => $description)
                            <label
                                class="cursor-pointer rounded-xl border p-3 transition-colors {{ $privacyValue === $value ? 'border-paw bg-paw/5' : 'border-whisker/30 hover:bg-warm-white' }}">
                                <div class="flex items-start gap-2">
                                    <input type="radio" name="privacy" value="{{ $value }}"
                                        class="mt-1 text-paw focus:ring-paw" @checked($privacyValue === $value)>
                                    <span>
                                        <span
                                            class="block text-sm font-semibold text-bark">{{ \Illuminate\Support\Str::headline($value) }}</span>
                                        <span class="mt-0.5 block text-xs text-fur">{{ $description }}</span>
                                    </span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('privacy')" class="mt-2"  />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <x-ui.label for="description" class="!mb-0">Description</x-ui.label>
                        <span class="text-xs text-fur" x-text="`${description.length}/5000`"></span>
                    </div>
                    <x-ui.textarea id="description" name="description" rows="5"
                        x-model="description">{{ old('description', $group->description ?? '') }}</x-ui.textarea>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <x-ui.label for="rules" class="!mb-0">Group Rules</x-ui.label>
                        <span class="text-xs text-fur" x-text="`${rules.length}/5000`"></span>
                    </div>
                    <x-ui.textarea id="rules" name="rules" rows="4"
                        x-model="rules">{{ old('rules', $group->rules ?? '') }}</x-ui.textarea>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-3">
                        <x-ui.label for="avatar" class="!mb-0">Avatar</x-ui.label>
                        <div
                            class="flex items-center gap-3 rounded-xl border border-whisker/30 p-3 bg-warm-white bg-opacity-50">
                            <div
                                class="h-16 w-16 overflow-hidden rounded-full border border-whisker/30 bg-warm-white flex items-center justify-center">
                                <img x-show="avatarSrc" x-cloak :src="avatarSrc" alt="Group avatar preview"
                                    class="h-full w-full object-cover">
                                <span x-show="!avatarSrc" class="text-xl text-fur">🐾</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <input id="avatar" name="avatar" type="file" accept="image/*"
                                    class="block w-full text-sm text-fur file:mr-4 file:rounded-full file:border-0 file:bg-paw/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-paw hover:file:bg-paw/20 cursor-pointer"
                                    @change="setAvatarPreview($event)">
                                <p class="mt-1 text-xs text-fur">Square image recommended.</p>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('avatar')" class="mt-2"  />
                    </div>

                    <div class="space-y-3">
                        <x-ui.label for="cover" class="!mb-0">Cover</x-ui.label>
                        <div
                            class="h-28 overflow-hidden rounded-xl border border-whisker/30 bg-warm-white relative group">
                            <img x-show="coverSrc" x-cloak :src="coverSrc" alt="Group cover preview"
                                class="h-full w-full object-cover">
                            <div x-show="!coverSrc" class="flex h-full items-center justify-center text-sm text-fur">No
                                cover selected</div>

                            <div
                                class="absolute inset-0 bg-bark/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <label for="cover"
                                    class="cursor-pointer bg-warm-white text-bark px-3 py-1.5 rounded-lg text-sm font-semibold shadow-sm hover:bg-cream transition-colors">
                                    Change Cover
                                </label>
                            </div>
                        </div>
                        <input id="cover" name="cover" type="file" accept="image/*" class="sr-only"
                            @change="setCoverPreview($event)">
                        <x-input-error :messages="$errors->get('cover')" class="mt-2"  />
                    </div>
                </div>

                <div>
                    <x-ui.input id="cover_image_path" name="cover_image_path" type="url"
                        label="Cover URL (optional fallback)" :value="old('cover_image_path', $group->cover_image_path ?? '')" placeholder="https://example.com/cover.jpg"  />
                </div>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-6 mt-6 border-t border-whisker/30">
                <div>
                    @if (!empty($canDelete))
                        <form method="POST" action="{{ route('groups.destroy', $groupRouteKey) }}"
                            onsubmit="return confirm('Wait! Are you incredibly sure you want to delete this group? All posts, members, and events will be deleted permanently.');">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger">Delete Group</x-ui.button>
                        </form>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button href="{{ route('groups.show', $groupRouteKey) }}" variant="ghost">Cancel</x-ui.button>
                    <x-ui.button type="submit" form="edit-group-form" variant="primary">Save Changes</x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.card>
</x-app-layout>