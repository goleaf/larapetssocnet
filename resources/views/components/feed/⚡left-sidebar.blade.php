<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    /**
     * @return array{
     *     user: User,
     *     ownedPets: Collection<int, Pet>
     * }
     */
    public function viewData(): array
    {
        $user = $this->viewer();

        $user->setAttribute('feed_followers_count', $user->acceptedFollowers()->count());
        $user->setAttribute('feed_following_count', $user->acceptedFollowing()->count());
        $user->setAttribute('feed_pets_count', $user->pets()->count());

        return [
            'user' => $user,
            'ownedPets' => $user->pets()
                ->without(['user', 'species', 'breed', 'media', 'tags'])
                ->select(['pets.id', 'pets.user_id', 'pets.name', 'pets.slug', 'pets.species', 'pets.breed'])
                ->orderBy('pets.name')
                ->get(),
        ];
    }

    private function viewer(): User
    {
        $viewer = auth()->user();

        abort_unless($viewer instanceof User, 403);

        return $viewer;
    }
};
?>

@placeholder
    <aside class="hidden space-y-4 lg:block lg:sticky lg:top-24 lg:self-start" data-ui="feed-left-sidebar-skeleton">
        <x-ui.card padding="base" class="animate-pulse">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-full bg-whisker/30"></div>
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="h-4 w-32 rounded-full bg-whisker/30"></div>
                    <div class="h-3 w-24 rounded-full bg-whisker/20"></div>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-3 gap-2">
                @for ($index = 0; $index < 3; $index++)
                    <div class="h-16 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                @endfor
            </div>
        </x-ui.card>

        <x-ui.card padding="base" class="animate-pulse">
            <div class="h-4 w-24 rounded-full bg-whisker/30"></div>
            <div class="mt-4 space-y-3">
                @for ($index = 0; $index < 3; $index++)
                    <div class="h-10 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                @endfor
            </div>
        </x-ui.card>
    </aside>
@endplaceholder

@php($data = $this->viewData())
@php($user = $data['user'])

<aside class="hidden space-y-4 lg:block lg:sticky lg:top-24 lg:self-start" data-ui="feed-left-sidebar">
    <x-ui.card padding="base">
        <div class="flex items-center gap-3">
            <x-ui.avatar :src="$user->avatar_url" :name="$user->name" :user="$user" size="lg"/>
            <div class="min-w-0">
                <a href="{{ route('profile.show', $user) }}" class="block truncate text-sm font-bold text-bark hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                    {{ $user->name }}
                </a>
                <p class="truncate text-xs text-fur">&#64;{{ $user->username }}</p>
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-[var(--radius-soft)] bg-cream px-2 py-3">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-fur">{{ __('feed.stats.followers') }}</dt>
                <dd class="mt-1 text-base font-bold text-bark">{{ number_format((int) ($user->feed_followers_count ?? 0)) }}</dd>
            </div>
            <div class="rounded-[var(--radius-soft)] bg-cream px-2 py-3">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-fur">{{ __('feed.stats.following') }}</dt>
                <dd class="mt-1 text-base font-bold text-bark">{{ number_format((int) ($user->feed_following_count ?? 0)) }}</dd>
            </div>
            <div class="rounded-[var(--radius-soft)] bg-cream px-2 py-3">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-fur">{{ __('feed.stats.pets') }}</dt>
                <dd class="mt-1 text-base font-bold text-bark">{{ number_format((int) ($user->feed_pets_count ?? 0)) }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card padding="base">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.my_pets_title') }}</p>
            <button type="button" x-on:click.prevent="window.openPetCreateWizard('feed-left-sidebar')" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                {{ __('feed.add_pet_shortcut') }}
            </button>
        </div>

        <div class="space-y-2.5">
            @forelse ($data['ownedPets'] as $pet)
                <a href="{{ route('pets.show', ['pet' => $pet->slug ?? $pet->getKey()]) }}" class="ui-list-item flex items-center gap-3 px-3 py-2 group">
                    <x-ui.avatar :name="$pet->name" size="sm"/>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-bark transition-colors group-hover:text-paw">{{ $pet->name }}</p>
                        <p class="truncate text-xs text-fur">{{ collect([$pet->species, $pet->breed])->filter()->join(' · ') }}</p>
                    </div>
                </a>
            @empty
                <p class="text-sm leading-6 text-fur">{{ __('feed.no_pets_shortcut') }}</p>
            @endforelse
        </div>
    </x-ui.card>
</aside>
