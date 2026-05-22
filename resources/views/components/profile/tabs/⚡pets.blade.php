<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetVisibilityService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public int $profileUserId;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;
    }

    /**
     * @return array{profileUser: User, canViewPets: bool, pets: Collection<int, Pet>}
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $canViewPets = app(PetVisibilityService::class)->canViewPetsForOwner($viewer, $profileUser);

        return [
            'profileUser' => $profileUser,
            'canViewPets' => $canViewPets,
            'pets' => $canViewPets ? $this->profilePetsQuery($profileUser, $viewer)->get() : collect(),
        ];
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
        return $user->pets()
            ->visibleTo($viewer)
            ->without(['user', 'species', 'breed', 'tags'])
            ->with('media')
            ->latest('pets.created_at');
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-pets" aria-busy="true">
 <x-ui.card>
 <div class="grid gap-4 sm:grid-cols-2">
 <div class="h-28 animate-pulse rounded-xl bg-cream"></div>
 <div class="h-28 animate-pulse rounded-xl bg-cream"></div>
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-pets">
 @if ($data['canViewPets'])
 <x-ui.card>
 <div class="grid gap-4 sm:grid-cols-2">
 @forelse ($data['pets'] as $pet)
 @php
 $petRouteParam = $pet->slug ?? $pet->getKey();
 @endphp
 <a href="{{ route('pets.show', ['pet'=> $petRouteParam]) }}"
 class="block min-h-28 rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="md"/>
 <div class="min-w-0">
 <p class="truncate text-base font-semibold text-bark">{{ $pet->name }}</p>
 <p class="truncate text-xs text-fur">
 {{ $pet->species }}{{ $pet->breed ?'·'. $pet->breed :''}}</p>
 </div>
 </div>
 @if ($pet->bio)
 <p class="mt-3 line-clamp-2 text-sm text-fur">{{ $pet->bio }}</p>
 @endif
 </a>
 @empty
 <div class="col-span-full">
 <x-ui.empty-state icon="🐾" title="No pets yet"
 description="This user has not added pets to their profile."/>
 </div>
 @endforelse
 </div>
 </x-ui.card>
 @else
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Pets are private"
 description="This profile does not share pet details with your current access level."/>
 </x-ui.card>
 @endif
</div>
