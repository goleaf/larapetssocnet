<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetFollowService;
use App\Services\PetVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public int $profileUserId;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;
    }

    /**
     * @return array{profileUser: User, viewer: User|null, canViewPets: bool, pets: Collection<int, Pet>}
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $canViewPets = app(PetVisibilityService::class)->canViewPetsForOwner($viewer, $profileUser);

        return [
            'profileUser' => $profileUser,
            'viewer' => $viewer,
            'canViewPets' => $canViewPets,
            'pets' => $canViewPets ? $this->profilePetsQuery($profileUser, $viewer)->get() : collect(),
        ];
    }

    public function followPet(int $petId, PetFollowService $petFollowService): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $pet = Pet::query()
            ->visibleTo($viewer)
            ->where('pets.user_id', $this->profileUserId)
            ->whereKey($petId)
            ->firstOrFail();

        Gate::forUser($viewer)->authorize('follow', $pet);

        $petFollowService->follow($viewer, $pet);
    }

    private function profileUser(): User
    {
        return User::query()->whereKey($this->profileUserId)->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    /**
     * @return HasMany<Pet, User>
     */
    private function profilePetsQuery(User $user, ?User $viewer): HasMany
    {
        $query = $user->pets()
            ->visibleTo($viewer)
            ->without(['user', 'species', 'breed', 'tags'])
            ->with('media')
            ->latest('pets.created_at');

        if ($viewer instanceof User) {
            $query->withExists([
                'followers as viewer_is_following' => function (Builder $followersQuery) use ($viewer): void {
                    $followersQuery->whereKey($viewer->getKey());
                },
            ]);
        } else {
            $query->selectRaw('0 as viewer_is_following');
        }

        return $query;
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-pets" aria-busy="true">
 <x-ui.card>
 <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
 <div class="h-72 animate-pulse rounded-[var(--radius-card)] bg-cream"></div>
 <div class="hidden h-72 animate-pulse rounded-[var(--radius-card)] bg-cream md:block"></div>
 <div class="hidden h-72 animate-pulse rounded-[var(--radius-card)] bg-cream xl:block"></div>
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-pets">
 @if ($data['canViewPets'])
 <div data-ui="profile-pet-card-grid" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
 @forelse ($data['pets'] as $pet)
 @php
 $petRouteParam = $pet->slug ?? $pet->getKey();
 $petImage = $pet->getFirstMediaUrl(Pet::MEDIA_COLLECTION_AVATAR) ?: $pet->avatar_url;
 $petSpecies = Str::headline((string) $pet->species);
 $petBreed = trim((string) ($pet->breed ?? ''));
 $petSubtitle = collect([$petSpecies, $petBreed])->filter()->join(' · ');
 $petAge = $pet->age_formatted ?: 'Age not set';
 $petFollowersCount = (int) ($pet->followers_count ?? 0);
 $viewerIsFollowing = (bool) data_get($pet, 'viewer_is_following', false);
 $canShowFollowPet = $data['viewer'] instanceof User
 && ! $data['viewer']->is($data['profileUser'])
 && ! $viewerIsFollowing;
 @endphp
 <article data-ui="profile-pet-card" class="shell-card ui-card-interactive flex min-h-full flex-col overflow-hidden p-0">
 <a href="{{ route('pets.show', ['pet'=> $petRouteParam]) }}"
 class="group block focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-label="View profile for {{ $pet->name }}">
 <div class="aspect-square w-full overflow-hidden bg-cream">
 @if (filled($petImage))
 <img src="{{ $petImage }}" alt="{{ $pet->name }} profile photo" class="h-full w-full object-cover transition-[scale] duration-200 group-hover:scale-[1.02]" loading="lazy">
 @else
 <div class="flex h-full w-full items-center justify-center text-5xl text-fur" aria-hidden="true">🐾</div>
 @endif
 </div>
 <div class="p-4 pb-3">
 <h3 class="truncate font-display text-lg font-bold leading-tight text-bark group-hover:text-paw">{{ $pet->name }}</h3>
 <p class="mt-1 truncate text-sm text-fur">{{ $petSubtitle }}</p>
 <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
 <div>
 <dt class="text-[11px] font-semibold uppercase text-fur">Age</dt>
 <dd class="mt-0.5 truncate font-semibold text-bark">{{ $petAge }}</dd>
 </div>
 <div>
 <dt class="text-[11px] font-semibold uppercase text-fur">Followers</dt>
 <dd class="mt-0.5 truncate font-semibold text-bark">{{ number_format($petFollowersCount) }}</dd>
 </div>
 </dl>
 </div>
 </a>
 @if ($canShowFollowPet)
 <div class="mt-auto border-t border-whisker/30 px-4 py-3">
 <x-ui.button
 type="button"
 variant="primary"
 size="sm"
 full
 data-ui="profile-pet-follow-action"
 wire:click="followPet({{ $pet->getKey() }})"
 wire:loading.attr="disabled"
 wire:target="followPet({{ $pet->getKey() }})"
 aria-label="Follow {{ $pet->name }}"
 >
 <span wire:loading.remove wire:target="followPet({{ $pet->getKey() }})">Follow Pet</span>
 <span wire:loading wire:target="followPet({{ $pet->getKey() }})">Following...</span>
 </x-ui.button>
 </div>
 @endif
 </article>
 @empty
 <div class="col-span-full">
 <x-ui.empty-state icon="🐾" title="No pets yet"
 description="This user has not added pets to their profile."/>
 </div>
 @endforelse
 </div>
 @else
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Pets are private"
 description="This profile does not share pet details with your current access level."/>
 </x-ui.card>
 @endif
</div>
