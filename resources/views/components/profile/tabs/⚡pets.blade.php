<?php

use App\Actions\Pets\CreatePetAction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetFollowService;
use App\Services\PetVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $profileUserId;

    public string $name = '';

    public string $species = 'dog';

    public ?string $breed = null;

    public string $sex = 'unknown';

    public ?string $birth_date = null;

    public ?string $age_text = null;

    public ?string $size = null;

    public ?string $bio = null;

    public ?string $personality_tags = null;

    public bool $is_public = true;

    public bool $is_adoptable = false;

    public bool $is_deceased = false;

    public mixed $avatar = null;

    /**
     * @var array<int, UploadedFile>
     */
    public array $gallery_photos = [];

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

    public function createPet(CreatePetAction $createPetAction): void
    {
        $viewer = $this->viewer();
        $profileUser = $this->profileUser();

        abort_unless($viewer instanceof User && $viewer->is($profileUser), 403);

        Gate::forUser($viewer)->authorize('create', Pet::class);

        $validated = $this->validate();

        $pet = $createPetAction->handle(
            $viewer,
            $this->petPayload($validated),
            $this->avatar instanceof UploadedFile ? $this->avatar : null,
            $this->gallery_photos
        );

        $this->resetPetForm();
        $this->resetValidation();

        $this->dispatch('profile-pet-created', petId: $pet->getKey());
        $this->js("window.toggleModal('profile-pet-create-modal', false)");
    }

    /**
     * @return array{followed: bool, followers_count: int}
     */
    #[Renderless]
    public function followPet(int $petId, PetFollowService $petFollowService): array
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

        return [
            'followed' => true,
            'followers_count' => (int) $pet->followers_count,
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

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $galleryMaxUpload = (int) config('pets.gallery.max_upload', 5);
        $galleryMaxFileSize = (int) config('pets.gallery.max_file_size_kb', 5120);
        $galleryMimes = implode(',', (array) config('pets.gallery.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp', 'gif']));

        return [
            'name' => ['required', 'string', 'max:50'],
            'species' => ['required', 'string', Rule::in(Pet::SPECIES)],
            'breed' => ['nullable', 'string', 'max:120'],
            'sex' => ['nullable', Rule::in(Pet::GENDERS)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'age_text' => ['nullable', 'string', 'max:50'],
            'size' => ['nullable', Rule::in(Pet::SIZES)],
            'bio' => ['nullable', 'string', 'max:500'],
            'personality_tags' => ['nullable', 'string', 'max:300'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'is_public' => ['boolean'],
            'is_adoptable' => ['boolean'],
            'is_deceased' => ['boolean'],
            'gallery_photos' => ['nullable', 'array', 'max:'.$galleryMaxUpload],
            'gallery_photos.*' => ['image', 'mimes:'.$galleryMimes, 'max:'.$galleryMaxFileSize],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function petPayload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'species' => $validated['species'],
            'breed' => $this->nullableString($validated['breed'] ?? null),
            'sex' => $validated['sex'] ?? 'unknown',
            'gender' => $validated['sex'] ?? 'unknown',
            'size' => $validated['size'] ?? null,
            'birthdate' => $validated['birth_date'] ?? null,
            'age_text' => $this->nullableString($validated['age_text'] ?? null),
            'bio' => $this->nullableString($validated['bio'] ?? null),
            'personality_tags' => $this->nullableString($validated['personality_tags'] ?? null),
            'is_public' => (bool) ($validated['is_public'] ?? false),
            'is_adoptable' => (bool) ($validated['is_adoptable'] ?? false),
            'is_deceased' => (bool) ($validated['is_deceased'] ?? false),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function resetPetForm(): void
    {
        $this->reset([
            'name',
            'breed',
            'birth_date',
            'age_text',
            'size',
            'bio',
            'personality_tags',
            'avatar',
            'gallery_photos',
            'is_adoptable',
            'is_deceased',
        ]);

        $this->species = 'dog';
        $this->sex = 'unknown';
        $this->is_public = true;
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
 $isOwner = $data['viewer'] instanceof User && $data['viewer']->is($data['profileUser']);
 $speciesOptions = collect(Pet::SPECIES)->map(static fn (string $species): array => ['value' => $species, 'label' => Str::headline($species)])->all();
 $genderOptions = collect(Pet::GENDERS)->map(static fn (string $gender): array => ['value' => $gender, 'label' => Str::headline($gender)])->all();
 $sizeOptions = collect(Pet::SIZES)->map(static fn (string $size): array => ['value' => $size, 'label' => Str::headline($size)])->all();
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-pets">
 @if ($data['canViewPets'])
 @if ($isOwner && $data['pets']->isEmpty())
 <section
 data-ui="profile-pet-owner-empty"
 class="shell-card flex flex-col items-center gap-6 border-2 border-dashed border-whisker/60 bg-cream/45 px-5 py-10 text-center sm:px-8"
 aria-labelledby="profile-pet-owner-empty-title"
 >
 <div class="relative h-32 w-40" aria-hidden="true">
 <div class="absolute left-1/2 top-8 h-20 w-28 -translate-x-1/2 rounded-[var(--radius-card)] border border-whisker/50 bg-warm-white shadow-card"></div>
 <div class="absolute left-8 top-3 h-12 w-12 rotate-[-8deg] rounded-[var(--radius-card)] border border-whisker/50 bg-sage/20"></div>
 <div class="absolute right-7 top-6 h-14 w-14 rotate-[10deg] rounded-[var(--radius-card)] border border-whisker/50 bg-paw-light/40"></div>
 <div class="absolute left-1/2 top-12 flex h-16 w-16 -translate-x-1/2 items-center justify-center rounded-full border-2 border-dashed border-paw/60 bg-warm-white text-3xl text-paw">🐾</div>
 <div class="absolute bottom-1 left-5 h-3 w-20 rounded-full bg-whisker/20"></div>
 <div class="absolute bottom-1 right-7 h-3 w-12 rounded-full bg-whisker/20"></div>
 </div>

 <div class="max-w-2xl space-y-3">
 <p class="text-xs font-semibold uppercase text-paw">Start their story</p>
 <h3 id="profile-pet-owner-empty-title" class="font-display text-2xl font-bold text-bark">Add your first pet profile</h3>
 <p class="text-sm leading-6 text-fur">
 Pet profiles give each pet a dedicated place for photos, details, personality notes, follower updates, and future posts. Creating one helps friends recognize them across your profile and makes your PetSocial page feel complete.
 </p>
 </div>

 <x-ui.button
 type="button"
 variant="primary"
 size="lg"
 class="min-h-12 px-6"
 aria-haspopup="dialog"
 aria-controls="pet-create-wizard"
 @click="window.openPetCreateWizard('profile-pets-empty')"
 >
 Add your first pet
 </x-ui.button>
 </section>
 @else
 <div data-ui="profile-pet-card-grid" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
 @if ($isOwner)
 <button
 type="button"
 wire:key="profile-add-pet-card"
 data-ui="profile-add-pet-card"
 class="shell-card ui-card-interactive flex min-h-72 flex-col items-center justify-center border-2 border-dashed border-whisker/60 bg-cream/45 p-6 text-center transition-all hover:-translate-y-0.5 hover:border-paw hover:bg-paw-light/30 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 aria-haspopup="dialog"
 aria-controls="pet-create-wizard"
 @click="window.openPetCreateWizard('profile-pets-card')"
 >
 <span class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-dashed border-paw/60 bg-warm-white text-5xl font-light leading-none text-paw" aria-hidden="true">+</span>
 <span class="mt-4 font-display text-lg font-bold text-bark">Add a pet</span>
 </button>
 @endif

 @foreach ($data['pets'] as $pet)
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
 <article
 wire:key="profile-pet-card-{{ $pet->getKey() }}"
 data-ui="profile-pet-card"
 x-data="petFollowCard({ petId: @js($pet->getKey()), petName: @js($pet->name), followed: @js($viewerIsFollowing), followersCount: @js($petFollowersCount) })"
 class="shell-card ui-card-interactive flex min-h-full flex-col overflow-hidden p-0">
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
 <dd class="mt-0.5 truncate font-semibold text-bark" data-ui="profile-pet-followers-count" x-text="formatCount(count)">{{ number_format($petFollowersCount) }}</dd>
 </div>
 </dl>
 </div>
 </a>
 @if ($canShowFollowPet)
 <div class="mt-auto border-t border-whisker/30 px-4 py-3">
 <button
 type="button"
 data-ui="profile-pet-follow-action"
 class="btn-base h-[var(--control-height-sm)] w-full px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60"
 x-bind:class="buttonClass"
 x-bind:disabled="busy"
 x-bind:aria-disabled="followed.toString()"
 x-bind:aria-busy="busy.toString()"
 x-bind:aria-pressed="followed.toString()"
 x-bind:aria-label="label + ' ' + petName"
 aria-label="Follow {{ $pet->name }}"
 @click="follow($wire)"
 >
 <span x-text="label">Follow Pet</span>
 </button>
 </div>
 @endif
 </article>
 @endforeach

 @if (!$isOwner && $data['pets']->isEmpty())
 <div class="col-span-full">
 <x-ui.empty-state icon="🐾" title="No pets yet"
 description="This user has not added pets to their profile."/>
 </div>
 @endif
 </div>
 @endif

 @else
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Pets are private"
 description="This profile does not share pet details with your current access level."/>
 </x-ui.card>
 @endif
</div>
