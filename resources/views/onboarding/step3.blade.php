<x-app-layout>
 <x-slot name="header">
 <div>
 <h1 class="shell-title text-xl">{{ __('en.onboarding_step_3_of_3') }}</h1>
 <p class="mt-1 text-sm shell-text-muted">{{ __('en.follow_people_and_join_groups_to_jump_start_your_community') }}</p>
 </div>
 </x-slot>

 <div class="space-y-5">
 <form method="POST" action="{{ route('onboarding.store', ['step'=> 3]) }}" class="space-y-5">
 @csrf

 <section class="shell-card p-6">
 <h2 class="shell-title text-lg">{{ __('en.suggested_people') }}</h2>
 <p class="mt-1 text-sm shell-text-muted">{{ __('en.select_anyone_you_d_like_to_follow_now') }}</p>

 <div class="mt-4 space-y-3">
 @forelse ($suggestedUsers as $suggestedUser)
 <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
 <div class="min-w-0">
 <p class="truncate font-semibold">{{ $suggestedUser->name }}</p>
 <p class="truncate text-xs shell-text-muted">
 &#64;{{ $suggestedUser->username }} · {{ __('en.param_followers', ['count' => $suggestedUser->followers_count]) }}
 </p>
 </div>
 <div class="flex items-center gap-2">
 @if (in_array($suggestedUser->id, $followingIds, true))
 <span class="chip">{{ __('en.already_following') }}</span>
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
 :title="__('en.no_suggestions_yet')"
 :description="__('en.as_more_members_join_we_ll_suggest_profiles_for_you_here')"
 class="mt-4"
  />
 @endforelse
 </div>
 </section>

 <section class="shell-card p-6">
 <h2 class="shell-title text-lg">{{ __('en.suggested_groups') }}</h2>
 <p class="mt-1 text-sm shell-text-muted">{{ __('en.choose_groups_that_match_your_interests') }}</p>

 <div class="mt-4 space-y-3">
 @forelse ($suggestedGroups as $group)
 <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
 <div class="min-w-0">
 <p class="truncate font-semibold">{{ $group->name }}</p>
 <p class="truncate text-xs shell-text-muted">
 {{ __('en.param_members', ['count' => $group->members_count]) }} · {{ $group->description }}
 </p>
 </div>
 <div class="flex items-center gap-2">
 @if (in_array($group->id, $joinedGroupIds, true))
 <span class="chip">{{ __('en.already_joined') }}</span>
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
 :title="__('en.no_groups_available')"
 :description="__('en.groups_will_appear_here_once_they_are_created')"
 class="mt-4"
  />
 @endforelse
 </div>
 </section>

 <x-input-error :messages="$errors->get('follow_user_ids')" class="mt-2" />
 <x-input-error :messages="$errors->get('join_group_ids')" class="mt-2" />

 <div class="flex flex-wrap items-center justify-between gap-3">
 <button type="submit" class="btn-base btn-primary">{{ __('en.finish_onboarding') }}</button>
 <button type="submit" form="skip-step-3" class="btn-base btn-ghost">{{ __('en.skip_and_finish') }}</button>
 </div>
 </form>

 <form id="skip-step-3" method="POST" action="{{ route('onboarding.skip', ['step'=> 3]) }}">
 @csrf
 </form>
 </div>
</x-app-layout>
