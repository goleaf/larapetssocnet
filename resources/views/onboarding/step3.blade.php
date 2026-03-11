<x-app-layout>
 <x-slot name="header">
 <div>
 <h1 class="shell-title text-xl">Onboarding: Step 3 of 3</h1>
 <p class="mt-1 text-sm shell-text-muted">Follow people and join groups to jump-start your community.</p>
 </div>
 </x-slot>

 <div class="space-y-5">
 <form method="POST"action="{{ route('onboarding.store', ['step'=> 3]) }}"class="space-y-5">
 @csrf

 <section class="shell-card p-6">
 <h2 class="shell-title text-lg">Suggested People</h2>
 <p class="mt-1 text-sm shell-text-muted">Select anyone you'd like to follow now.</p>

 <div class="mt-4 space-y-3">
 @forelse ($suggestedUsers as $suggestedUser)
 <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
 <div class="min-w-0">
 <p class="truncate font-semibold">{{ $suggestedUser->name }}</p>
 <p class="truncate text-xs shell-text-muted">
 &#64;{{ $suggestedUser->username }} · {{ $suggestedUser->followers_count }} followers
 </p>
 </div>
 <div class="flex items-center gap-2">
 @if (in_array($suggestedUser->id, $followingIds, true))
 <span class="chip">Already following</span>
 @endif
 <input
 type="checkbox"
 name="follow_user_ids[]"
 value="{{ $suggestedUser->id }}"
 class="h-4 w-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
 >
 </div>
 </label>
 @empty
 <x-empty-state
 icon="👋"
 title="No suggestions yet"
 description="As more members join, we'll suggest profiles for you here."
 class="mt-4"
 />
 @endforelse
 </div>
 </section>

 <section class="shell-card p-6">
 <h2 class="shell-title text-lg">Suggested Groups</h2>
 <p class="mt-1 text-sm shell-text-muted">Choose groups that match your interests.</p>

 <div class="mt-4 space-y-3">
 @forelse ($suggestedGroups as $group)
 <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
 <div class="min-w-0">
 <p class="truncate font-semibold">{{ $group->name }}</p>
 <p class="truncate text-xs shell-text-muted">
 {{ $group->members_count }} members · {{ $group->description }}
 </p>
 </div>
 <div class="flex items-center gap-2">
 @if (in_array($group->id, $joinedGroupIds, true))
 <span class="chip">Already joined</span>
 @endif
 <input
 type="checkbox"
 name="join_group_ids[]"
 value="{{ $group->id }}"
 class="h-4 w-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
 >
 </div>
 </label>
 @empty
 <x-empty-state
 icon="👥"
 title="No groups available"
 description="Groups will appear here once they are created."
 class="mt-4"
 />
 @endforelse
 </div>
 </section>

 <x-input-error :messages="$errors->get('follow_user_ids')"class="mt-2"/>
 <x-input-error :messages="$errors->get('join_group_ids')"class="mt-2"/>

 <div class="flex flex-wrap items-center justify-between gap-3">
 <button type="submit"class="btn-base btn-primary">Finish Onboarding</button>
 <button type="submit"form="skip-step-3"class="btn-base btn-ghost">Skip and Finish</button>
 </div>
 </form>

 <form id="skip-step-3"method="POST"action="{{ route('onboarding.skip', ['step'=> 3]) }}">
 @csrf
 </form>
 </div>
</x-app-layout>
