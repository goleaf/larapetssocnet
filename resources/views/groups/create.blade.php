<x-app-layout>
    @php
        $privacyValue = old('privacy', $selectedPrivacy ?? 'public');
        $speciesValue = old('species', data_get($group, 'species', 'all_pets'));

        $privacyOptions = [
            ['value' => 'public', 'label' => 'Public', 'description' => 'Anyone can discover and join instantly.'],
            ['value' => 'private', 'label' => 'Private', 'description' => 'Visible in search, but new members need approval.'],
            ['value' => 'secret', 'label' => 'Secret', 'description' => 'Hidden from discovery and joinable by invite only.'],
        ];

        $speciesOptions = [
            ['value' => 'all_pets', 'label' => 'All Pets'],
            ['value' => 'dogs', 'label' => 'Dogs'],
            ['value' => 'cats', 'label' => 'Cats'],
            ['value' => 'birds', 'label' => 'Birds'],
            ['value' => 'small_pets', 'label' => 'Small Pets'],
            ['value' => 'reptiles', 'label' => 'Reptiles'],
            ['value' => 'aquatic', 'label' => 'Aquatic'],
            ['value' => 'mixed', 'label' => 'Mixed'],
        ];
    @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="Create Group"
            subtitle="Create Community"
            :breadcrumbs="[
                ['label' => 'Groups', 'href' => route('groups.index')],
                ['label' => 'Create'],
            ]"
        >
            <x-slot name="action">
                <x-ui.button href="{{ route('groups.index') }}" variant="ghost" size="sm">Back to Groups</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <x-ui.card>
        <form method="POST" action="{{ route('groups.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <x-ui.form-section title="Basics" description="Set the group identity and focus.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input
                        class="md:col-span-2"
                        name="name"
                        label="Group Name"
                        required
                        maxlength="160"
                        :value="old('name', $group->name ?? '')"
                    />

                    <x-ui.select
                        class="md:col-span-2"
                        name="species"
                        label="Species Focus"
                        :options="$speciesOptions"
                        :selected="$speciesValue"
                    />
                </div>
            </x-ui.form-section>

            <x-ui.form-section title="Privacy" description="Choose how members discover and join.">
                <x-ui.radio-group
                    name="privacy"
                    label="Group Type"
                    :options="$privacyOptions"
                    :selected="$privacyValue"
                />
            </x-ui.form-section>

            <x-ui.form-section title="Content" description="Explain what this community is about.">
                <div class="space-y-4">
                    <x-ui.textarea
                        name="description"
                        label="Description"
                        rows="5"
                        maxlength="5000"
                        :value="old('description', $group->description ?? '')"
                    />

                    <x-ui.textarea
                        name="rules"
                        label="Group Rules"
                        rows="4"
                        maxlength="5000"
                        :value="old('rules', $group->rules ?? '')"
                    />
                </div>
            </x-ui.form-section>

            <x-ui.form-section title="Media" description="Add optional avatar and cover visuals.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.file-upload
                        name="avatar"
                        label="Avatar"
                        accept="image/*"
                        preview
                        max-size="5MB"
                        hint="Square image recommended."
                        :error="$errors->first('avatar')"
                    />

                    <x-ui.file-upload
                        name="cover"
                        label="Cover"
                        accept="image/*"
                        preview
                        max-size="8MB"
                        hint="Landscape image works best."
                        :error="$errors->first('cover')"
                    />
                </div>

                <x-ui.input
                    name="cover_image_path"
                    label="Cover URL (optional fallback)"
                    type="url"
                    :value="old('cover_image_path', $group->cover_image_path ?? '')"
                    placeholder="https://example.com/cover.jpg"
                />
            </x-ui.form-section>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button href="{{ route('groups.index') }}" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Create Group</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
