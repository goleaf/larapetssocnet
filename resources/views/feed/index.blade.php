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
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <x-ui.page-header title="Your Feed" subtitle="Home Feed" />
            </div>
            <div class="flex items-center gap-2">
                <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">Saved</x-ui.button>
                <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">Explore</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="space-y-4">
            <x-ui.card>
                <div class="mb-4 flex items-center gap-3">
                    <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md" />
                    <p class="text-sm font-semibold text-bark">Create a post</p>
                </div>

                <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <x-ui.label for="feed-post-body">Body</x-ui.label>
                        <x-ui.textarea id="feed-post-body" name="body" rows="3"
                            placeholder="Share an update about your pet..."
                            :error="$errors->first('body')">{{ old('body') }}</x-ui.textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="feed-post-pet-id">Pet</x-ui.label>
                            <x-ui.select id="feed-post-pet-id" name="pet_id" :error="$errors->first('pet_id')">
                                <option value="">No pet tag</option>
                                @foreach (auth()->user()->pets as $pet)
                                    <option value="{{ $pet->id }}" @selected((string) old('pet_id') === (string) $pet->id)>
                                        {{ $pet->name }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="feed-post-photos">Media</x-ui.label>
                            <input id="feed-post-photos" type="file" name="media[]" multiple
                                accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
                                class="w-full text-sm text-fur file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-paw-light file:text-paw-dark hover:file:bg-cream">
                            @error('media')
                                <x-ui.hint error :message="$message" />
                            @enderror
                            @error('media.*')
                                <x-ui.hint error :message="$message" />
                            @enderror
                        </div>
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
                    <x-ui.empty-state icon="🐾" title="Follow some pet owners to see their posts here!"
                        description="Explore the community to find interesting pets and users.">
                        <x-slot name="action">
                            <x-ui.button href="{{ route('explore.index', ['tab' => 'users']) }}" variant="secondary"
                                size="md">Explore pet owners</x-ui.button>
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
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-xs font-bold font-display uppercase tracking-wider text-fur">Your Groups</h4>
                    <a href="{{ route('groups.index', ['privacy' => 'joined']) }}"
                        class="text-xs font-semibold hover:underline text-paw">Browse</a>
                </div>

                <div class="space-y-1 -mx-2">
                    @forelse ($yourGroups as $group)
                        @php
                            $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
                        @endphp

                        <x-ui.user-row :name="$group->name" :subtitle="\Illuminate\Support\Str::headline((string) ($group->privacy ?? 'public'))" :href="route('groups.show', $groupRouteKey)" class="px-2">
                            <x-slot name="action">
                                <span
                                    class="text-xs text-fur">{{ number_format((int) ($group->members_count ?? 0)) }}</span>
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