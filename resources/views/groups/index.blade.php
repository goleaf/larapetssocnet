<x-app-layout>
    @php
        $privacyOptions = [
            ['value' => 'all', 'label' => 'All types'],
            ['value' => 'public', 'label' => 'Public'],
            ['value' => 'private', 'label' => 'Private'],
            ['value' => 'secret', 'label' => 'Secret'],
            ['value' => 'joined', 'label' => 'Joined'],
            ['value' => 'owned', 'label' => 'Owned'],
        ];

        $sortOptions = [
            ['value' => 'latest', 'label' => 'Latest'],
            ['value' => 'members', 'label' => 'Members'],
            ['value' => 'name', 'label' => 'Name'],
        ];
    @endphp

    <x-slot name="header">
        <x-ui.page-header title="Groups" subtitle="Find and join communities for pet lovers.">
            <x-slot name="action">
                @auth
                    <x-ui.button :href="route('groups.create')" variant="primary" size="sm">Create Group</x-ui.button>
                @endauth
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="space-y-5">
        <x-ui.card>
            <form method="GET" action="{{ route('groups.index') }}" class="grid gap-3 md:grid-cols-12">
                <x-ui.input
                    class="md:col-span-5"
                    name="q"
                    label="Search"
                    :value="$search"
                    placeholder="Search groups"
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

                <div class="flex items-end md:col-span-1">
                    <x-ui.button type="submit" variant="primary" size="sm" class="w-full">Apply</x-ui.button>
                </div>

                <div class="flex items-end md:col-span-1">
                    <x-ui.button :href="route('groups.index')" variant="ghost" size="sm" class="w-full">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($groups->isEmpty())
            <x-ui.card>
                <x-ui.empty-state
                    icon="👥"
                    title="No Groups Found"
                    description="Try a different search or filter option."
                />
            </x-ui.card>
        @else
            <p class="text-sm text-fur">{{ number_format($groups->total()) }} groups found</p>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($groups as $group)
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

            <x-ui.card>
                <x-ui.pagination :paginator="$groups" />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
