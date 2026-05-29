<?php

use App\Actions\Pets\CreatePetAction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\ContentService;
use App\Services\FollowService;
use App\Services\LocationAutocompleteService;
use App\Services\OnboardingSuggestionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.auth-register')]
#[Title('Welcome to PetSocial')]
class extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public ?string $bio = null;

    public ?string $location = null;

    public ?string $location_lat = null;

    public ?string $location_lng = null;

    /**
     * @var list<array{label: string, latitude: float, longitude: float}>
     */
    public array $locationSuggestions = [];

    public bool $locationSuggestionsOpen = false;

    public mixed $profileAvatar = null;

    public ?string $suggestedAvatarUrl = null;

    public bool $useSuggestedAvatar = false;

    public string $petName = '';

    public string $petSpecies = '';

    public ?string $petBreed = null;

    public ?string $petBirthDate = null;

    public string $petGender = 'unknown';

    public mixed $petPhoto = null;

    public ?int $createdPetId = null;

    public ?string $createdPetSpecies = null;

    public bool $petStepSkipped = false;

    public ?string $petSuccessMessage = null;

    /**
     * @var list<array{id: int, name: string, username: string|null, avatar_url: string|null, description: string, followers_count: int, status: string}>
     */
    public array $suggestions = [];

    public ?string $followMessage = null;

    public function mount(): void
    {
        $user = $this->currentUser();

        $this->step = $this->initialStep($user);
        $this->bio = $user->bio;
        $this->location = $user->location;
        $this->location_lat = $user->location_lat === null ? null : (string) $user->location_lat;
        $this->location_lng = $user->location_lng === null ? null : (string) $user->location_lng;
        $this->suggestedAvatarUrl = $this->providerAvatarUrl($user);

        if ($this->step === 3) {
            $this->refreshSuggestions();
        }
    }

    public function updatedLocation(): void
    {
        $this->location = $this->nullableString($this->location);
        $this->location_lat = null;
        $this->location_lng = null;

        if ($this->location === null || mb_strlen($this->location) < 2) {
            $this->locationSuggestions = [];
            $this->locationSuggestionsOpen = false;

            return;
        }

        $this->locationSuggestions = app(LocationAutocompleteService::class)
            ->suggest($this->location, (int) config('services.geocoding.limit', 5));
        $this->locationSuggestionsOpen = $this->locationSuggestions !== [];
    }

    public function updatedProfileAvatar(): void
    {
        $this->useSuggestedAvatar = false;
        $this->validateOnly('profileAvatar', $this->profileRules());
    }

    public function updatedPetPhoto(): void
    {
        $this->validateOnly('petPhoto', $this->petRules());
    }

    public function selectLocationSuggestion(int $index): void
    {
        $suggestion = $this->locationSuggestions[$index] ?? null;

        if (! is_array($suggestion)) {
            return;
        }

        $this->location = (string) $suggestion['label'];
        $this->location_lat = (string) $suggestion['latitude'];
        $this->location_lng = (string) $suggestion['longitude'];
        $this->locationSuggestionsOpen = false;
    }

    public function acceptSuggestedAvatar(): void
    {
        if ($this->suggestedAvatarUrl === null) {
            return;
        }

        $this->profileAvatar = null;
        $this->useSuggestedAvatar = true;
    }

    public function continueFromProfile(ContentService $contentService): void
    {
        $validated = $this->validate($this->profileRules());
        $user = $this->currentUser();
        $bio = $this->nullableString($validated['bio'] ?? null);

        $user->forceFill([
            'bio' => $bio,
            'bio_html' => $bio === null ? null : $contentService->process($bio),
            'location' => $this->nullableString($validated['location'] ?? null),
            'location_lat' => $validated['location_lat'] ?? null,
            'location_lng' => $validated['location_lng'] ?? null,
            'onboarding_step' => '2',
        ])->save();

        if ($this->profileAvatar instanceof UploadedFile) {
            $user->addMedia($this->profileAvatar)->toMediaCollection(User::MEDIA_COLLECTION_AVATAR);
        } elseif ($this->useSuggestedAvatar && $this->suggestedAvatarUrl !== null) {
            $user->forceFill(['avatar_path' => $this->suggestedAvatarUrl])->save();
        }

        $this->step = 2;
    }

    public function skipProfile(): void
    {
        $this->currentUser()->forceFill(['onboarding_step' => '2'])->save();
        $this->step = 2;
    }

    public function savePet(CreatePetAction $createPet): void
    {
        $validated = $this->validate($this->petRules(), $this->petMessages());

        $pet = $createPet->handle(
            owner: $this->currentUser(),
            attributes: [
                'name' => Str::squish((string) $validated['petName']),
                'species' => $validated['petSpecies'],
                'breed' => $this->nullableString($validated['petBreed'] ?? null),
                'date_of_birth' => $validated['petBirthDate'] ?? null,
                'gender' => $validated['petGender'],
                'sex' => $validated['petGender'],
                'visibility' => 'public',
            ],
            avatar: $this->petPhoto instanceof UploadedFile ? $this->petPhoto : null,
        );

        $this->createdPetId = (int) $pet->getKey();
        $this->createdPetSpecies = (string) $pet->species;
        $this->petStepSkipped = false;
        $this->petSuccessMessage = $pet->name.' has been added to your profile.';
        $this->currentUser()->forceFill(['onboarding_step' => '3'])->save();
        $this->step = 3;
        $this->refreshSuggestions();
    }

    public function skipPet(): void
    {
        $this->petStepSkipped = true;
        $this->petSuccessMessage = null;
        $this->currentUser()->forceFill(['onboarding_step' => '3'])->save();
        $this->step = 3;
        $this->refreshSuggestions();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function toggleFollow(int $targetId, FollowService $followService): void
    {
        $user = $this->currentUser();
        $index = $this->suggestionIndex($targetId);

        if ($index === null) {
            return;
        }

        $target = User::query()
            ->withPublicProfile()
            ->notBlockedFor($user)
            ->whereKey($targetId)
            ->first();

        if (! $target instanceof User) {
            return;
        }

        if (($this->suggestions[$index]['status'] ?? 'none') !== 'none') {
            $followService->unfollow($user, $target);
            $this->suggestions[$index]['status'] = 'none';

            return;
        }

        $this->suggestions[$index]['status'] = $followService->follow($user, $target);
    }

    public function followAll(FollowService $followService): void
    {
        $user = $this->currentUser();
        $targetIds = collect($this->suggestions)
            ->filter(fn (array $suggestion): bool => ($suggestion['status'] ?? 'none') === 'none')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($targetIds->isEmpty()) {
            return;
        }

        $targets = User::query()
            ->withPublicProfile()
            ->notBlockedFor($user)
            ->whereIn('users.id', $targetIds->all())
            ->get();

        $statuses = [];

        DB::transaction(function () use ($followService, $targets, $user, &$statuses): void {
            foreach ($targets as $target) {
                if (! $target instanceof User) {
                    continue;
                }

                $statuses[(int) $target->getKey()] = $followService->follow($user, $target);
            }
        });

        foreach ($this->suggestions as $index => $suggestion) {
            $targetId = (int) $suggestion['id'];

            if (isset($statuses[$targetId])) {
                $this->suggestions[$index]['status'] = $statuses[$targetId];
            }
        }
    }

    public function completeOnboarding(): void
    {
        $user = $this->currentUser();
        $hasPet = $this->createdPetId !== null || $user->pets()->exists();

        $user->markOnboardingComplete($this->petStepSkipped && ! $hasPet);

        $this->redirectRoute('feed.index', navigate: false);
    }

    public function progressPercentage(): int
    {
        return match ($this->step) {
            1 => 33,
            2 => 66,
            default => 100,
        };
    }

    public function stepName(): string
    {
        return match ($this->step) {
            1 => 'Profile',
            2 => 'First pet',
            default => 'Suggestions',
        };
    }

    public function profileAvatarPreview(): ?string
    {
        if ($this->profileAvatar instanceof UploadedFile) {
            try {
                return $this->profileAvatar->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        if ($this->useSuggestedAvatar) {
            return $this->suggestedAvatarUrl;
        }

        return null;
    }

    public function petPhotoPreview(): ?string
    {
        if (! $this->petPhoto instanceof UploadedFile) {
            return null;
        }

        try {
            return $this->petPhoto->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    public function followedCount(): int
    {
        return collect($this->suggestions)
            ->filter(fn (array $suggestion): bool => ($suggestion['status'] ?? 'none') !== 'none')
            ->count();
    }

    /**
     * @return array<string, string>
     */
    public function speciesOptions(): array
    {
        return collect(Pet::SPECIES)
            ->mapWithKeys(fn (string $species): array => [$species => Str::headline(str_replace('_', ' ', $species))])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function genderOptions(): array
    {
        return [
            'unknown' => 'Unknown',
            'female' => 'Female',
            'male' => 'Male',
        ];
    }

    private function refreshSuggestions(): void
    {
        $user = $this->currentUser();
        $species = $this->createdPetSpecies;

        if ($species === null && $this->createdPetId !== null) {
            $species = Pet::query()
                ->where('user_id', $user->getKey())
                ->whereKey($this->createdPetId)
                ->value('species');
        }

        $suggestions = app(OnboardingSuggestionService::class)->forUser($user, $species, 10);
        $statusMap = app(FollowService::class)->followStatusMap($user, $suggestions);

        $this->suggestions = $suggestions
            ->map(fn (User $suggested): array => [
                'id' => (int) $suggested->getKey(),
                'name' => (string) ($suggested->display_name ?: $suggested->name),
                'username' => $suggested->username,
                'avatar_url' => $suggested->avatar_url,
                'description' => (string) $suggested->getAttribute('suggestion_description'),
                'followers_count' => (int) $suggested->followers_count,
                'status' => $statusMap[(int) $suggested->getKey()] ?? 'none',
            ])
            ->values()
            ->all();
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function initialStep(User $user): int
    {
        $step = (string) ($user->onboarding_step ?? '1');

        if (is_numeric($step)) {
            return max(1, min(3, (int) $step));
        }

        return match ($step) {
            'pet', 'pets', 'step2', 'profile' => 2,
            'social', 'suggestions', 'step3' => 3,
            default => 1,
        };
    }

    private function providerAvatarUrl(User $user): ?string
    {
        $url = $user->socialAccounts()
            ->whereNotNull('provider_avatar_url')
            ->latest()
            ->value('provider_avatar_url');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function suggestionIndex(int $targetId): ?int
    {
        foreach ($this->suggestions as $index => $suggestion) {
            if ((int) $suggestion['id'] === $targetId) {
                return $index;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function profileRules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:120'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'profileAvatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function petRules(): array
    {
        return [
            'petName' => ['required', 'string', 'max:50'],
            'petSpecies' => ['required', 'string', Rule::in(Pet::SPECIES)],
            'petBreed' => ['nullable', 'string', 'max:120'],
            'petBirthDate' => ['nullable', 'date', 'before_or_equal:today'],
            'petGender' => ['required', Rule::in(Pet::GENDERS)],
            'petPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function petMessages(): array
    {
        return [
            'petName.required' => 'Pet name is required.',
            'petSpecies.required' => 'Choose a species.',
            'petSpecies.in' => 'Choose a supported species.',
            'petBirthDate.before_or_equal' => 'Date of birth cannot be in the future.',
        ];
    }
};
?>

<div class="flex min-h-screen w-full flex-col bg-[color:var(--surface-panel)] px-5 py-6 sm:min-h-0 sm:max-w-[48rem] sm:bg-transparent sm:px-0 sm:py-0" data-ui="onboarding-page">
 <header class="mx-auto w-full max-w-2xl pb-5 text-center sm:pb-6">
 <a href="{{ route('feed.index') }}" class="inline-flex min-h-11 items-center justify-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark" aria-hidden="true">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name', 'PetSocial') }}</span>
 </a>
 <h1 class="mt-4 shell-title text-2xl text-balance">Welcome to PetSocial</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">A few quick choices will make your first feed feel like yours.</p>
 </header>

 <section class="flex flex-1 flex-col bg-[color:var(--surface-panel)] sm:flex-none sm:rounded-[var(--radius-card)] sm:border sm:border-[var(--border-soft)] sm:p-6" data-ui="onboarding-panel">
 <div class="border-b border-whisker/30 px-0 pb-5">
 <div class="flex flex-wrap items-center justify-between gap-3">
 <div>
 <p class="shell-kicker">Step {{ $step }} of 3</p>
 <h2 class="mt-1 shell-title text-xl">{{ $this->stepName() }}</h2>
 </div>
 <span class="rounded-[var(--radius-soft)] bg-paw-light px-3 py-1 text-xs font-bold uppercase tracking-[0.08em] text-paw-dark">{{ $this->progressPercentage() }}%</span>
 </div>
 <div class="mt-4 h-2 overflow-hidden rounded-pill bg-whisker/25">
 <div class="h-full rounded-pill bg-paw transition-[width] duration-[400ms] ease-out" style="width: {{ $this->progressPercentage() }}%"></div>
 </div>
 </div>

 <div class="flex-1 py-6">
 @if ($step === 1)
 <div class="space-y-6">
 <div>
 <h3 class="shell-title text-2xl">Let's set up your profile.</h3>
 </div>

 <div class="grid gap-4 sm:grid-cols-[13rem_minmax(0,1fr)] sm:items-start">
 @if ($suggestedAvatarUrl)
 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4 text-center">
 <img src="{{ $suggestedAvatarUrl }}" alt="Suggested avatar" class="mx-auto h-24 w-24 rounded-pill border border-whisker/30 object-cover">
 <p class="mt-3 text-sm font-semibold text-bark">Suggested from social login</p>
 <button type="button" wire:click="acceptSuggestedAvatar" class="mt-3 inline-flex min-h-10 items-center justify-center rounded-[var(--radius-button)] bg-paw-light px-3 text-sm font-semibold text-paw-dark transition hover:bg-paw/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Use this photo
 </button>
 </div>
 @endif

 <div>
 <x-ui.label for="profile-avatar-upload">Profile photo</x-ui.label>
 <label for="profile-avatar-upload" class="mt-2 flex h-32 w-32 cursor-pointer items-center justify-center overflow-hidden rounded-pill border-2 border-dashed border-paw/30 bg-paw-light/50 text-center text-sm font-semibold text-paw-dark transition hover:border-paw focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-paw">
 @if ($this->profileAvatarPreview())
 <img src="{{ $this->profileAvatarPreview() }}" alt="Profile photo preview" class="h-full w-full object-cover">
 @else
 <span class="px-4">Upload photo</span>
 @endif
 <input id="profile-avatar-upload" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="profileAvatar">
 </label>
 <p class="mt-2 text-xs italic leading-5 text-fur">A clear photo helps friends recognize you when you follow, comment, and message.</p>
 @error('profileAvatar')
 <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
 @enderror
 </div>
 </div>

 <div>
 <x-ui.textarea
 id="onboarding-bio"
 label="Bio"
 rows="3"
 maxlength="160"
 wire:model.live.debounce.200ms="bio"
 :error="$errors->first('bio')"
 />
 <p class="mt-2 text-xs italic leading-5 text-fur">A short bio gives people context before they follow you.</p>
 </div>

 <div class="relative">
 <x-ui.label for="onboarding-location">Location</x-ui.label>
 <input
 id="onboarding-location"
 type="text"
 autocomplete="off"
 wire:model.live.debounce.400ms="location"
 class="form-input mt-1 h-[var(--control-height-md)] w-full text-sm focus:border-paw @error('location') border-rose text-rose focus:border-rose @enderror"
 aria-autocomplete="list"
 aria-expanded="{{ $locationSuggestionsOpen ? 'true' : 'false' }}"
 aria-controls="onboarding-location-suggestions"
 @error('location') aria-invalid="true" @enderror
 >
 <input type="hidden" wire:model="location_lat">
 <input type="hidden" wire:model="location_lng">
 <p class="mt-2 text-xs italic leading-5 text-fur">Location makes local events, groups, and adoption posts more relevant.</p>
 <div class="mt-1 min-h-5 text-xs" aria-live="polite">
 <span wire:loading wire:target="location" class="text-fur">Searching locations...</span>
 @error('location')
 <span wire:loading.remove wire:target="location" class="font-medium text-danger">{{ $message }}</span>
 @enderror
 @if ($errors->has('location_lat') || $errors->has('location_lng'))
 <span wire:loading.remove wire:target="location" class="font-medium text-danger">Select a valid location suggestion.</span>
 @endif
 </div>
 @if ($locationSuggestionsOpen && $locationSuggestions !== [])
 <ul id="onboarding-location-suggestions" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-[var(--radius-control)] border border-whisker/50 bg-warm-white py-1 shadow-card" role="listbox">
 @foreach ($locationSuggestions as $index => $suggestion)
 <li role="option" wire:key="onboarding-location-suggestion-{{ $index }}">
 <button type="button" class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm text-bark transition-colors hover:bg-cream focus-visible:bg-cream focus-visible:outline-none" wire:click="selectLocationSuggestion({{ $index }})">
 <span class="font-medium">{{ $suggestion['label'] }}</span>
 <span class="text-xs text-fur">{{ number_format($suggestion['latitude'], 4) }}, {{ number_format($suggestion['longitude'], 4) }}</span>
 </button>
 </li>
 @endforeach
 </ul>
 @endif
 </div>
 </div>
 @elseif ($step === 2)
 <div class="space-y-6">
 <div>
 <h3 class="shell-title text-2xl">Add your first pet.</h3>
 <p class="mt-2 text-sm leading-6 text-fur">PetSocial is built around pets. Adding your first pet helps us personalize your experience and connect you with a community that cares about the same animals you do.</p>
 </div>

 @if ($petSuccessMessage)
 <x-ui.alert type="success">{{ $petSuccessMessage }}</x-ui.alert>
 @endif

 <div class="grid gap-4 sm:grid-cols-2">
 <x-ui.input id="onboarding-pet-name" label="Pet name" wire:model.live.debounce.200ms="petName" :error="$errors->first('petName')" />
 <x-ui.select id="onboarding-pet-species" label="Species" placeholder="Choose species" :options="$this->speciesOptions()" wire:model.live="petSpecies" :error="$errors->first('petSpecies')" />
 <x-ui.input id="onboarding-pet-breed" label="Breed" wire:model.live.debounce.200ms="petBreed" :error="$errors->first('petBreed')" />
 <x-ui.input id="onboarding-pet-birth-date" type="date" label="Date of birth" wire:model.live="petBirthDate" :error="$errors->first('petBirthDate')" />
 <x-ui.select id="onboarding-pet-gender" label="Gender" :options="$this->genderOptions()" wire:model.live="petGender" :error="$errors->first('petGender')" />
 <div>
 <x-ui.label for="onboarding-pet-photo">Photo</x-ui.label>
 <label for="onboarding-pet-photo" class="mt-1 flex min-h-28 cursor-pointer items-center justify-center overflow-hidden rounded-[var(--radius-card)] border-2 border-dashed border-paw/30 bg-paw-light/50 text-center text-sm font-semibold text-paw-dark transition hover:border-paw focus-within:outline-2 focus-within:outline-offset-2 focus-visible:outline-paw">
 @if ($this->petPhotoPreview())
 <img src="{{ $this->petPhotoPreview() }}" alt="Pet photo preview" class="h-32 w-full object-cover">
 @else
 <span class="px-4">Upload pet photo</span>
 @endif
 <input id="onboarding-pet-photo" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="petPhoto">
 </label>
 @error('petPhoto')
 <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
 @enderror
 </div>
 </div>
 </div>
 @else
 <div class="space-y-6">
 <div>
 <h3 class="shell-title text-2xl">Follow some accounts to get your feed started.</h3>
 <p class="mt-2 text-sm leading-6 text-fur">We've found some accounts based on your interests. Follow at least a few to make your feed come alive.</p>
 </div>

 <div class="max-h-[28rem] space-y-3 overflow-y-auto pr-1 [scrollbar-gutter:stable]" data-ui="onboarding-suggestions-list">
 @forelse ($suggestions as $suggestion)
 <article wire:key="onboarding-suggestion-{{ $suggestion['id'] }}" class="rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4">
 <div class="flex items-start gap-3">
 <a href="{{ route('profile.show', ['user' => $suggestion['username']]) }}" class="shrink-0 rounded-pill focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$suggestion['avatar_url']" :name="$suggestion['name']" size="md" />
 </a>
 <div class="min-w-0 flex-1">
 <div class="flex flex-wrap items-center justify-between gap-2">
 <div class="min-w-0">
 <a href="{{ route('profile.show', ['user' => $suggestion['username']]) }}" class="block truncate text-sm font-bold text-bark hover:text-paw">{{ $suggestion['name'] }}</a>
 <p class="truncate text-xs text-fur">{{ $suggestion['username'] ? '@'.$suggestion['username'] : 'PetSocial member' }}</p>
 </div>
 <button
 type="button"
 wire:click="toggleFollow({{ $suggestion['id'] }})"
 wire:loading.attr="disabled"
 wire:target="toggleFollow({{ $suggestion['id'] }})"
 class="inline-flex min-h-10 min-w-24 items-center justify-center rounded-[var(--radius-button)] px-3 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60 {{ $suggestion['status'] === 'none' ? 'bg-paw text-white hover:bg-paw-dark' : 'bg-paw-light text-paw-dark hover:bg-paw/20' }}"
 >
 <span wire:loading wire:target="toggleFollow({{ $suggestion['id'] }})" class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
 <span wire:loading.remove wire:target="toggleFollow({{ $suggestion['id'] }})">{{ $suggestion['status'] === 'none' ? 'Follow' : 'Following' }}</span>
 </button>
 </div>
 <p class="mt-2 text-sm leading-6 text-fur">{{ $suggestion['description'] }}</p>
 <p class="mt-2 text-xs font-semibold text-whisker">{{ number_format($suggestion['followers_count']) }} followers</p>
 </div>
 </div>
 </article>
 @empty
 <x-ui.empty-state title="Suggestions are still warming up" description="As more members join, this list will fill with accounts worth following." />
 @endforelse
 </div>
 </div>
 @endif
 </div>

 <div class="border-t border-whisker/30 pt-5">
 @if ($step === 1)
 <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
 <button type="button" wire:click="skipProfile" class="min-h-11 text-sm font-semibold text-fur underline-offset-4 hover:text-bark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Skip this step</button>
 <x-ui.button type="button" variant="primary" wire:click="continueFromProfile" wire:loading.attr="disabled" wire:target="continueFromProfile">
 <span wire:loading.remove wire:target="continueFromProfile">Continue</span>
 <span wire:loading wire:target="continueFromProfile">Saving...</span>
 </x-ui.button>
 </div>
 @elseif ($step === 2)
 <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
 <button type="button" wire:click="back" class="min-h-11 text-sm font-semibold text-fur underline-offset-4 hover:text-bark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Back</button>
 <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
 <button type="button" wire:click="skipPet" class="min-h-11 text-sm font-semibold text-fur underline-offset-4 hover:text-bark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Skip — I'll add a pet later</button>
 <x-ui.button type="button" variant="primary" wire:click="savePet" wire:loading.attr="disabled" wire:target="savePet">
 <span wire:loading.remove wire:target="savePet">Continue</span>
 <span wire:loading wire:target="savePet">Saving pet...</span>
 </x-ui.button>
 </div>
 </div>
 @else
 <div class="space-y-4">
 <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
 <button type="button" wire:click="back" class="min-h-11 text-sm font-semibold text-fur underline-offset-4 hover:text-bark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Back</button>
 <x-ui.button type="button" variant="secondary" wire:click="followAll" wire:loading.attr="disabled" wire:target="followAll">Follow all</x-ui.button>
 </div>

 @if ($this->followedCount() === 0)
 <p class="rounded-[var(--radius-soft)] bg-amber-light px-3 py-2 text-sm font-medium text-amber">Following at least a few accounts will make your feed much more interesting!</p>
 @endif

 <x-ui.button type="button" variant="primary" full wire:click="completeOnboarding" wire:loading.attr="disabled" wire:target="completeOnboarding">
 <span wire:loading.remove wire:target="completeOnboarding">Get started</span>
 <span wire:loading wire:target="completeOnboarding">Opening your feed...</span>
 </x-ui.button>
 </div>
 @endif
 </div>
 </section>
</div>
