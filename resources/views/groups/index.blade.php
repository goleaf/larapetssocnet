<x-app-layout>
    @php
        $selectedSpecies = request()->string('species')->toString();
        if ($selectedSpecies === '') {
            $selectedSpecies = 'all_pets';
        }

        $speciesTabs = [
            'all_pets' => 'All Pets',
            'dogs' => 'Dogs',
            'cats' => 'Cats',
            'birds' => 'Birds',
            'small_pets' => 'Small Pets',
            'reptiles' => 'Reptiles',
            'aquatic' => 'Aquatic',
            'mixed' => 'Mixed',
        ];

        $speciesTabItems = collect($speciesTabs)
            ->map(static fn(string $label, string $value): array => ['label' => $label, 'value' => $value])
            ->values()
            ->all();

        $visibleGroups = $groups->getCollection()->filter(function ($group) use ($selectedSpecies): bool {
            if ($selectedSpecies === 'all_pets') {
                return true;
            }

            $groupSpecies = strtolower(str_replace(['-', ' '], '_', (string) data_get($group, 'species', 'mixed')));

            return $groupSpecies === $selectedSpecies;
        })->values();

        $privacyOptions = [
            ['value' => 'all', 'label' => 'All types'],
            ['value' => 'public', 'label' => 'Public'],
            ['value' => 'private', 'label' => 'Private'],
            ['value' => 'secret', 'label' => 'Secret'],
        ];

        if (auth()->check()) {
            $privacyOptions[] = ['value' => 'joined', 'label' => 'Joined'];
            $privacyOptions[] = ['value' => 'owned', 'label' => 'Owned'];
        }

        $sortOptions = [
            ['value' => 'latest', 'label' => 'Latest'],
            ['value' => 'members', 'label' => 'Members'],
            ['value' => 'name', 'label' => 'Name'],
        ];
    @endphp

    <x-slot name="header">
        <x-ui.page-header title="Groups" subtitle="Find Communities">
            <x-slot name="action">
                @auth
                    <x-ui.button href="{{ route('groups.create') }}" variant="primary" size="sm">Create Group</x-ui.button>
                @endauth
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="space-y-5">
        <x-ui.card>
            <form method="GET" action="{{ route('groups.index') }}" class="grid gap-3 md:grid-cols-12">
                <input type="hidden" name="species" value="{{ $selectedSpecies }}">

                <x-ui.input
                    class="md:col-span-5"
                    name="q"
                    label="Search"
                    :value="$search"
                    placeholder="Search groups, interests, breeds..."
                />

                <x-ui.select
                    class="md:col-span-3"
                    name="privacy"
                    label="Type"
                    :options="$privacyOptions"
                    :selected="$privacy"
                />

                <x-ui.select
                    class="md:col-span-2"
                    name="sort"
                    label="Sort"
                    :options="$sortOptions"
                    :selected="$sort"
                />

                <div class="flex items-end md:col-span-2">
                    <x-ui.button type="submit" class="w-full" variant="primary" size="sm">Apply</x-ui.button>
                </div>
            </form>

            <x-ui.divider class="my-5" />

            <x-ui.tabs :tabs="$speciesTabItems" :active="$selectedSpecies" param-name="species" class="mb-0" />

            <x-ui.card class="mt-5" padding="sm">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Type Legend</p>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-fur">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.group-type-badge type="public" />
                        Anyone can join.
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.group-type-badge type="private" />
                        Requires approval.
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.group-type-badge type="secret" />
                        Invite-only.
                    </span>
                </div>
            </x-ui.card>
        </x-ui.card>

        <section>
            @if ($visibleGroups->isEmpty())
                <x-ui.empty-state
                    title="No Groups Found"
                    description="Try a different species tab or search term."
                />
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($visibleGroups as $group)
                        @php
                            $ownerId = $group->owner_user_id ?? $group->owner_id;
                            $owner = $owners->get($ownerId);
                            $membership = $membershipByGroup->get($group->id);
                        @endphp

                        @include('partials.group-card', [
                            'group' => $group,
                            'owner' => $owner,
                            'membership' => $membership,
                        ])
                    @endforeach
                </div>
            @endif
        </section>

        @if ($groups->hasPages())
            <x-ui.card>
                <x-ui.pagination :paginator="$groups" />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
