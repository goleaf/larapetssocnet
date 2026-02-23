@section('title', 'Feed — PetSocial')

<x-app-layout>
    @php
        $yourGroups = collect();

        try {
            if (
                auth()->check()
                && \Illuminate\Support\Facades\Schema::hasTable('groups')
                && \Illuminate\Support\Facades\Schema::hasTable('group_members')
            ) {
                $yourGroups = \App\Models\Group::query()
                    ->whereIn('groups.id', function ($query): void {
                        $query->select('group_members.group_id')
                            ->from('group_members')
                            ->where('group_members.user_id', auth()->id())
                            ->where(function ($statusQuery): void {
                                $statusQuery->whereNull('group_members.status')
                                    ->orWhereIn('group_members.status', ['active', 'accepted']);
                            });
                    })
                    ->orderByDesc('groups.members_count')
                    ->limit(6)
                    ->get();
            }
        } catch (\Throwable) {
            $yourGroups = collect();
        }

        $postMediaError = $errors->first('media') ?: $errors->first('media.*');
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <x-ui.page-header title="Your Feed" subtitle="Home Feed" class="mb-0" />

            <div class="flex items-center gap-2">
                <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">Saved</x-ui.button>
                <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">Explore</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="space-y-4">
            <x-ui.card>
                <x-slot name="header">
                    <x-ui.card-header title="Create a post" subtitle="Share something about your pet today.">
                        <x-slot name="action">
                            <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md" />
                        </x-slot>
                    </x-ui.card-header>
                </x-slot>

                <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <x-ui.textarea
                        name="body"
                        label="Body"
                        rows="3"
                        placeholder="Share an update about your pet..."
                        :error="$errors->first('body')"
                    >{{ old('body') }}</x-ui.textarea>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-ui.select name="pet_id" label="Pet" :error="$errors->first('pet_id')">
                            <option value="">No pet tag</option>
                            @foreach (auth()->user()->pets as $pet)
                                <option value="{{ $pet->id }}" @selected((string) old('pet_id') === (string) $pet->id)>
                                    {{ $pet->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.file-upload
                            name="media"
                            label="Media"
                            multiple
                            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
                            :error="$postMediaError"
                            hint="Upload up to 4 files."
                        />
                    </div>

                    <div class="flex items-center justify-end">
                        <x-ui.button type="submit" variant="primary" size="md">Post</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.alert type="info">
                Feed note: private group posts only appear once you are an approved member of that group.
            </x-ui.alert>

            <div role="feed" aria-label="Pet feed" class="space-y-4">
                @forelse ($posts as $post)
                    @include('partials.post-card', ['post' => $post, 'viewer' => $user])
                @empty
                    <x-ui.empty-state
                        icon="🐾"
                        title="Follow some pet owners to see their posts here!"
                        description="Explore the community to find interesting pets and users."
                    >
                        <x-slot name="action">
                            <x-ui.button href="{{ route('explore.index', ['tab' => 'users']) }}" variant="secondary" size="md">
                                Explore pet owners
                            </x-ui.button>
                        </x-slot>
                    </x-ui.empty-state>
                @endforelse
            </div>

            @if ($posts->hasPages())
                <x-ui.card>
                    <x-ui.pagination :paginator="$posts" />
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <x-ui.card>
                <x-slot name="header">
                    <x-ui.card-header title="Your Groups" subtitle="Communities you are active in">
                        <x-slot name="action">
                            <x-ui.button href="{{ route('groups.index', ['privacy' => 'joined']) }}" variant="ghost" size="xs">Browse</x-ui.button>
                        </x-slot>
                    </x-ui.card-header>
                </x-slot>

                <div class="space-y-1 -mx-2">
                    @forelse ($yourGroups as $group)
                        @php
                            $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
                        @endphp

                        <x-ui.user-row
                            :name="$group->name"
                            :subtitle="\Illuminate\Support\Str::headline((string) ($group->privacy ?? 'public'))"
                            :href="route('groups.show', $groupRouteKey)"
                            class="px-2"
                        >
                            <x-slot name="action">
                                <span class="text-xs text-fur">{{ number_format((int) ($group->members_count ?? 0)) }}</span>
                            </x-slot>
                        </x-ui.user-row>
                    @empty
                        <p class="text-sm text-fur px-2">You have not joined any groups yet.</p>
                    @endforelse
                </div>

                @auth
                    <div class="mt-4">
                        <x-ui.button href="{{ route('groups.create') }}" variant="primary" full>Create a Group</x-ui.button>
                    </div>
                @endauth
            </x-ui.card>
        </aside>
    </div>
</x-app-layout>
