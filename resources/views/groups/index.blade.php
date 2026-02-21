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

        $visibleGroups = $groups->getCollection()->filter(function ($group) use ($selectedSpecies): bool {
            if ($selectedSpecies === 'all_pets') {
                return true;
            }

            $groupSpecies = strtolower(str_replace(['-', ' '], '_', (string) data_get($group, 'species', 'mixed')));

            return $groupSpecies === $selectedSpecies;
        })->values();
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Find Communities</p>
                <h2 class="shell-title text-xl leading-tight">Groups</h2>
            </div>

            @auth
                <a href="{{ route('groups.create') }}" class="btn-base btn-primary px-3 py-2 text-sm">Create Group</a>
            @endauth
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <x-flash-message type="success" :message="session('status')" />
        @endif

        <section class="shell-card space-y-4 p-4 sm:p-5">
            <form method="GET" action="{{ route('groups.index') }}" class="grid gap-3 md:grid-cols-12">
                <input type="hidden" name="species" value="{{ $selectedSpecies }}">

                <div class="md:col-span-5">
                    <label for="q" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Search</label>
                    <x-text-input id="q" name="q" type="text" class="block w-full" :value="$search" placeholder="Search groups, interests, breeds..." />
                </div>

                <div class="md:col-span-3">
                    <label for="privacy" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Type</label>
                    <select id="privacy" name="privacy" class="form-select">
                        <option value="all" @selected($privacy === 'all')>All types</option>
                        <option value="public" @selected($privacy === 'public')>Public</option>
                        <option value="private" @selected($privacy === 'private')>Private</option>
                        <option value="secret" @selected($privacy === 'secret')>Secret</option>
                        @auth
                            <option value="joined" @selected($privacy === 'joined')>Joined</option>
                            <option value="owned" @selected($privacy === 'owned')>Owned</option>
                        @endauth
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="sort" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Sort</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="latest" @selected($sort === 'latest')>Latest</option>
                        <option value="members" @selected($sort === 'members')>Members</option>
                        <option value="name" @selected($sort === 'name')>Name</option>
                    </select>
                </div>

                <div class="flex items-end md:col-span-2">
                    <button type="submit" class="btn-base btn-primary w-full px-3 py-2 text-sm">Apply</button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2" aria-label="Species filters">
                @foreach ($speciesTabs as $value => $label)
                    <a
                        href="{{ route('groups.index', array_merge(request()->except('page', 'species'), ['species' => $value])) }}"
                        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors {{ $selectedSpecies === $value ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-[color:var(--ui-border)] text-[color:var(--ui-text)] hover:bg-[color:var(--ui-surface-muted)]' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="rounded-xl border border-[color:var(--ui-border)] p-3">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Type Legend</p>
                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Public: anyone can join</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Private: requires approval</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Secret: invite-only</span>
                </div>
            </div>
        </section>

        <section>
            @if ($visibleGroups->isEmpty())
                <x-empty-state
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
            <section class="shell-card p-4">
                {{ $groups->onEachSide(1)->links() }}
            </section>
        @endif
    </div>
</x-app-layout>
