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
                <p class="shell-kicker">Home Feed</p>
                <h1 class="shell-title text-2xl leading-tight">Your Feed</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('saved.index') }}" class="btn-base btn-ghost px-3 py-2 text-xs sm:text-sm">Saved</a>
                <a href="{{ route('explore.index') }}" class="btn-base btn-ghost px-3 py-2 text-xs sm:text-sm">Explore</a>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="space-y-4">
            <section class="shell-card p-4 sm:p-5">
                <div class="mb-4 flex items-center gap-3">
                    <x-avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md" />
                    <p class="text-sm font-semibold" style="color: var(--ui-text);">Create a post</p>
                </div>

                <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <label for="feed-post-body" class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Body</label>
                        <textarea
                            id="feed-post-body"
                            name="body"
                            rows="3"
                            class="form-textarea"
                            placeholder="Share an update about your pet..."
                        >{{ old('body') }}</textarea>
                        @error('body')
                            <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label for="feed-post-pet-id" class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Pet</label>
                            <select id="feed-post-pet-id" name="pet_id" class="form-select">
                                <option value="">No pet tag</option>
                                @foreach (auth()->user()->pets as $pet)
                                    <option value="{{ $pet->id }}" @selected((string) old('pet_id') === (string) $pet->id)>
                                        {{ $pet->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('pet_id')
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="feed-post-photos" class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Media</label>
                            <input
                                id="feed-post-photos"
                                type="file"
                                name="media[]"
                                multiple
                                accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
                                class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100"
                            >
                            @error('media')
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                            @error('media.*')
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="submit" class="btn-base btn-primary px-4 py-2 text-sm">Post</button>
                    </div>
                </form>
            </section>

            <section class="shell-card border border-[color:var(--ui-border)] p-3">
                <p class="text-xs shell-text-muted">
                    Feed note: private group posts only appear once you are an approved member of that group.
                </p>
            </section>

            <div role="feed" aria-label="Pet feed" class="space-y-4">
                @forelse ($posts as $post)
                    @include('partials.post-card', ['post' => $post, 'viewer' => $user])
                @empty
                    <section class="shell-card p-8 text-center">
                        <p class="text-base font-semibold" style="color: var(--ui-text);">Follow some pet owners to see their posts here! 🐾</p>
                        <a href="{{ route('explore.index', ['tab' => 'users']) }}" class="mt-4 inline-flex btn-base btn-secondary px-4 py-2 text-sm">
                            Explore pet owners
                        </a>
                    </section>
                @endforelse
            </div>

            @if ($posts->hasPages())
                <div class="shell-card p-4">
                    {{ $posts->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <section class="shell-card p-4">
                <div class="mb-2 flex items-center justify-between">
                    <p class="shell-kicker">Your Groups</p>
                    <a href="{{ route('groups.index', ['privacy' => 'joined']) }}" class="text-xs font-semibold hover:underline" style="color: var(--ui-primary);">Browse</a>
                </div>

                <div class="space-y-2.5">
                    @forelse ($yourGroups as $group)
                        @php
                            $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
                        @endphp

                        <a href="{{ route('groups.show', $groupRouteKey) }}" class="hover-lift flex items-center justify-between rounded-xl border px-3 py-2" style="border-color: var(--ui-border);">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" style="color: var(--ui-text);">{{ $group->name }}</p>
                                <p class="truncate text-xs shell-text-muted">{{ \Illuminate\Support\Str::headline((string) ($group->privacy ?? 'public')) }}</p>
                            </div>
                            <span class="text-xs shell-text-muted">{{ number_format((int) ($group->members_count ?? 0)) }}</span>
                        </a>
                    @empty
                        <p class="text-sm shell-text-muted">You have not joined any groups yet.</p>
                    @endforelse
                </div>

                @auth
                    <a href="{{ route('groups.create') }}" class="btn-base btn-primary mt-3 w-full px-3 py-2 text-sm">Create a Group</a>
                @endauth
            </section>
        </aside>
    </div>
</x-app-layout>
