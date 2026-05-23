<?php

namespace App\Http\Controllers\Pets;

use App\Actions\Pets\CreatePetAction;
use App\Actions\Pets\DeletePetAction;
use App\Actions\Pets\UpdatePetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\CreatePetRequest;
use App\Http\Requests\Pets\UpdatePetRequest;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthLog;
use App\Models\Pets\PetMilestone;
use App\Services\ChartService;
use App\Services\PersonalityTagService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class PetController extends Controller
{
    public function __construct(private ChartService $chartService) {}

    public function index(Request $request): View
    {
        $pets = Pet::query()
            ->public()
            ->visibleTo($request->user())
            ->latest('created_at')
            ->cursorPaginate(12)
            ->withQueryString();

        return view('pets.index', [
            'pets' => $pets,
        ]);
    }

    public function show(Request $request, Pet $pet): View
    {
        $this->authorize('view', $pet);

        $pet->loadMissing(['user', 'species', 'breed', 'media', 'tags']);

        $isOwner = $pet->isOwnedBy($request->user());
        $canViewTimelineContent = Gate::allows('viewPosts', $pet);

        $tabs = $canViewTimelineContent ? ['posts', 'gallery', 'milestones'] : ['about'];

        if (($pet->adoption_status ?? 'not_listed') !== 'not_listed' || (bool) $pet->is_adoptable) {
            $tabs[] = 'adopt';
        }

        if (! in_array('about', $tabs, true)) {
            $tabs[] = 'about';
        }

        if ($isOwner) {
            $tabs[] = 'health';
        }

        $activeTab = $request->string('tab')->toString() ?: 'posts';

        if (! in_array($activeTab, $tabs, true)) {
            $activeTab = 'posts';
        }

        $posts = $canViewTimelineContent ? $this->postsForShow($pet, $request->user()) : collect();
        $gallery = $activeTab === 'gallery'
            ? $pet->galleryForShow()
                ->map(fn (mixed $item): array => $this->mapGalleryItem($item))
                ->values()
            : collect();
        $milestones = $activeTab === 'milestones'
            ? PetMilestone::query()
                ->forPet($pet)
                ->with('user:id,name,username')
                ->chronological()
                ->get()
            : collect();

        $healthLogs = collect();
        $weightChartSvg = null;

        if ($isOwner && $activeTab === 'health') {
            $healthLogs = $pet->recentHealthLogs();
            $weightChartSvg = $this->chartService->weightChart($pet->weight_logs);
        }

        $isFollowing = $request->user()?->isFollowingPet($pet) ?? false;
        $pet->setAttribute('viewer_is_following', $isFollowing);

        $petSlug = $pet->slug ?? $pet->getKey();
        $avatarUrl = $pet->avatar_url;
        $personalityTags = $this->normalizePersonalityTags($pet->personality_tags);
        $birthdateLabel = $this->resolveBirthdateLabel($pet);
        $ageLabel = $pet->age_formatted;
        $lifeStageLabel = $this->resolveLifeStageLabel($pet);
        $speciesLabel = $this->resolveSpeciesLabel($pet);
        $breedLabel = $this->resolveBreedLabel($pet);
        $sexLabel = $this->resolveSexLabel($pet);
        $postsCount = (int) ($pet->posts_count ?? 0);
        $followersCount = (int) ($pet->followers_count ?? 0);
        $featuredMilestones = $this->featuredMilestonesForShow($pet);
        $identityFacts = $this->identityFactsForShow(
            pet: $pet,
            speciesLabel: $speciesLabel,
            breedLabel: $breedLabel,
            sexLabel: $sexLabel,
            ageLabel: $ageLabel,
            lifeStageLabel: $lifeStageLabel,
        );
        $careSnapshot = $this->careSnapshotForShow($pet, $isOwner);

        return view('pets.show', [
            'pet' => $pet,
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'isFollowing' => $isFollowing,
            'canViewTimelineContent' => $canViewTimelineContent,
            'posts' => $posts,
            'gallery' => $gallery,
            'milestones' => $milestones,
            'healthLogs' => $healthLogs,
            'weightChartSvg' => $weightChartSvg,
            'qrCodeUrl' => route('pets.qr.show', $pet),
            'qrDownloadUrl' => route('pets.qr.download', $pet),
            'petSlug' => $petSlug,
            'avatarUrl' => $avatarUrl,
            'personalityTags' => $personalityTags,
            'birthdateLabel' => $birthdateLabel,
            'ageLabel' => $ageLabel,
            'lifeStageLabel' => $lifeStageLabel,
            'speciesLabel' => $speciesLabel,
            'breedLabel' => $breedLabel,
            'sexLabel' => $sexLabel,
            'postsCount' => $postsCount,
            'followersCount' => $followersCount,
            'featuredMilestones' => $featuredMilestones,
            'identityFacts' => $identityFacts,
            'careSnapshot' => $careSnapshot,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Pet::class);

        return view('pets.create', $this->petFormData(request()));
    }

    public function store(CreatePetRequest $request, CreatePetAction $createPetAction): RedirectResponse
    {
        $this->authorize('create', Pet::class);

        $pet = $createPetAction->handle(
            $request->user(),
            $this->payloadFromRequest($request),
            $request->file('avatar'),
            (array) $request->file('gallery_photos', [])
        );

        return redirect()
            ->route('pets.show', $pet)
            ->with('status', __('pets.flash.created'));
    }

    public function edit(Pet $pet): View
    {
        $this->authorize('update', $pet);

        $pet->loadMissing('media');

        $galleryItems = $this->buildGalleryManagerItems($pet);
        $galleryMax = (int) config('pets.gallery.max_photos', 30);
        $galleryUploadMax = (int) config('pets.gallery.max_upload', 5);
        $galleryCount = $galleryItems->count();
        $galleryRemaining = max($galleryMax - $galleryCount, 0);

        return view('pets.edit', [
            'pet' => $pet,
            'galleryItems' => $galleryItems,
            'galleryMax' => $galleryMax,
            'galleryUploadMax' => $galleryUploadMax,
            'galleryCount' => $galleryCount,
            'galleryRemaining' => $galleryRemaining,
            ...$this->petFormData(request(), $pet),
        ]);
    }

    public function update(UpdatePetRequest $request, Pet $pet, UpdatePetAction $updatePetAction): RedirectResponse
    {
        $this->authorize('update', $pet);

        $pet = $updatePetAction->handle(
            $pet,
            $this->payloadFromRequest($request),
            $request->file('avatar'),
            (array) $request->file('gallery_photos', [])
        );

        return redirect()
            ->route('pets.show', $pet)
            ->with('status', __('pets.flash.updated'));
    }

    public function destroy(Request $request, Pet $pet, DeletePetAction $deletePetAction): RedirectResponse
    {
        $this->authorize('delete', $pet);

        $deletePetAction->handle($request->user(), $pet);

        return redirect()
            ->route('pets.index')
            ->with('status', __('pets.flash.deleted'));
    }

    public function explore(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';
        $isAdoptableFilter = ($request->filled('is_adoptable') || $request->filled('is_for_adoption'))
            ? ($request->boolean('is_adoptable') || $request->boolean('is_for_adoption'))
            : null;
        $personalityTags = $this->normalizePersonalityTagsFilter($request->input('personality_tags'));

        $pets = Pet::paginateExploreCatalog([
            'q' => $search,
            'species' => (string) $request->string('species'),
            'breed' => (string) $request->string('breed'),
            'sex' => (string) $request->string('sex'),
            'personality_tags' => $personalityTags,
            'is_adoptable' => $isAdoptableFilter,
            'sort' => $sort,
        ], $request->user());

        return view('pets.explore', [
            'pets' => $pets,
            'filters' => [
                'q' => $search,
                'species' => (string) $request->string('species'),
                'breed' => (string) $request->string('breed'),
                'sex' => (string) $request->string('sex'),
                'personality_tags' => $personalityTags,
                'is_adoptable' => $isAdoptableFilter,
                'sort' => $sort,
            ],
            'personalityTagSuggestions' => app(PersonalityTagService::class)->getSuggestions(),
        ]);
    }

    public function adopt(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';
        $personalityTags = $this->normalizePersonalityTagsFilter($request->input('personality_tags'));
        $pets = Pet::paginateAdoptionCatalog([
            'q' => $search,
            'species' => (string) $request->string('species'),
            'sex' => (string) $request->string('sex'),
            'personality_tags' => $personalityTags,
            'sort' => $sort,
        ], $request->user());

        return view('pets.adopt', [
            'pets' => $pets,
            'filters' => [
                'q' => $search,
                'species' => (string) $request->string('species'),
                'sex' => (string) $request->string('sex'),
                'personality_tags' => $personalityTags,
                'sort' => $sort,
            ],
            'personalityTagSuggestions' => app(PersonalityTagService::class)->getSuggestions(),
        ]);
    }

    /**
     * @return CursorPaginator<int, Post>
     */
    private function postsForShow(Pet $pet, ?User $viewer): CursorPaginator
    {
        return Post::query()
            ->select([
                'posts.id',
                'posts.user_id',
                'posts.pet_id',
                'posts.body',
                'posts.body_html',
                'posts.status',
                'posts.published_at',
                'posts.created_at',
            ])
            ->where('posts.pet_id', $pet->getKey())
            ->published()
            ->visibleTo($viewer)
            ->latest('posts.created_at')
            ->cursorPaginate(12)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(Request $request): array
    {
        $validated = method_exists($request, 'validated') ? $request->validated() : [];

        return [
            'name' => $validated['name'] ?? null,
            'species' => $validated['species'] ?? null,
            'breed' => $validated['breed'] ?? null,
            'sex' => $validated['sex'] ?? ($validated['gender'] ?? 'unknown'),
            'gender' => $validated['gender'] ?? ($validated['sex'] ?? 'unknown'),
            'size' => $validated['size'] ?? null,
            'birthdate' => $validated['birth_date'] ?? ($validated['date_of_birth'] ?? null),
            'age_text' => $validated['age_text'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'personality_tags' => $validated['personality_tags'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'is_adoptable' => $request->boolean('is_adoptable') || $request->boolean('is_for_adoption'),
            'is_deceased' => $request->boolean('is_deceased'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function petFormData(Request $request, ?Pet $pet = null): array
    {
        $service = app(PersonalityTagService::class);
        $speciesOptions = $this->resolveSelectOptions(Pet::SPECIES);
        $genderOptions = $this->resolveSelectOptions(Pet::GENDERS);
        $sizeOptions = $this->resolveSelectOptions(Pet::SIZES);

        return [
            'personalityTagSuggestions' => $service->getSuggestions(),
            'personalityTagMax' => $service->maxTags(),
            'personalityTagsInitial' => $this->resolvePersonalityTagsInitial($request, $pet),
            'birthdateValue' => $this->resolveBirthdateValue($request, $pet),
            'speciesOptions' => $speciesOptions,
            'genderOptions' => $genderOptions,
            'sizeOptions' => $sizeOptions,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizePersonalityTagsFilter(mixed $rawTags): array
    {
        if ($rawTags === null || $rawTags === '') {
            return [];
        }

        return app(PersonalityTagService::class)->normalize($rawTags);
    }

    /**
     * @return array<int, string>
     */
    private function resolvePersonalityTagsInitial(Request $request, ?Pet $pet): array
    {
        $tags = $request->old('personality_tags');

        if ($tags === null && $pet) {
            $tags = $pet->personality_tags ?? [];
        }

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        return collect($tags ?? [])
            ->map(static fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveBirthdateValue(Request $request, ?Pet $pet): ?string
    {
        $birthdateValue = $request->old('birth_date', $request->old('birthdate'));

        if ($birthdateValue !== null && $birthdateValue !== '') {
            return (string) $birthdateValue;
        }

        if (! $pet instanceof Pet) {
            return null;
        }

        $rawBirthdate = data_get($pet, 'birth_date') ?? data_get($pet, 'birthdate');

        if ($rawBirthdate instanceof CarbonInterface) {
            return $rawBirthdate->toDateString();
        }

        if (is_string($rawBirthdate) && $rawBirthdate !== '') {
            try {
                return Carbon::parse($rawBirthdate)->toDateString();
            } catch (Throwable) {
                return substr($rawBirthdate, 0, 10);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function resolveSelectOptions(array $values): array
    {
        $options = ['' => 'Select'];

        foreach ($values as $value) {
            $options[$value] = Str::headline($value);
        }

        return $options;
    }

    private function resolveBirthdateLabel(Pet $pet): ?string
    {
        $birthdate = $pet->birth_date ?? $pet->date_of_birth;

        if ($birthdate instanceof CarbonInterface) {
            return $birthdate->toFormattedDateString();
        }

        if (is_string($birthdate) && $birthdate !== '') {
            try {
                return Carbon::parse($birthdate)->toFormattedDateString();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolveLifeStageLabel(Pet $pet): ?string
    {
        $birthdate = $pet->birth_date ?? $pet->date_of_birth;

        if (! $birthdate instanceof CarbonInterface) {
            if (is_string($birthdate) && $birthdate !== '') {
                try {
                    $birthdate = Carbon::parse($birthdate);
                } catch (Throwable) {
                    return null;
                }
            } else {
                return null;
            }
        }

        $months = $this->completedMonthsSince($birthdate);
        $species = strtolower((string) ($pet->species ?? ''));

        return match ($species) {
            'dog' => $months < 12 ? 'Puppy' : ($months >= 84 ? 'Senior dog' : 'Adult dog'),
            'cat' => $months < 12 ? 'Kitten' : ($months >= 120 ? 'Senior cat' : 'Adult cat'),
            'bird' => $months < 12 ? 'Young bird' : ($months >= 96 ? 'Senior bird' : 'Adult bird'),
            'rabbit', 'hamster' => $months < 6 ? 'Young '.Str::headline($species) : ($months >= 48 ? 'Senior '.Str::headline($species) : 'Adult '.Str::headline($species)),
            'fish', 'reptile' => $months < 12 ? 'Young '.Str::headline($species) : 'Adult '.Str::headline($species),
            default => $months < 12 ? 'Young companion' : ($months >= 96 ? 'Senior companion' : 'Adult companion'),
        };
    }

    private function completedMonthsSince(CarbonInterface $date): int
    {
        $today = now();
        $months = (((int) $today->format('Y') - (int) $date->format('Y')) * 12)
            + ((int) $today->format('n') - (int) $date->format('n'));

        if ((int) $today->format('j') < (int) $date->format('j')) {
            $months--;
        }

        return max(0, $months);
    }

    /**
     * @return Collection<int, PetMilestone>
     */
    private function featuredMilestonesForShow(Pet $pet): Collection
    {
        return PetMilestone::query()
            ->forPet($pet)
            ->latest('occurred_on')
            ->latest('id')
            ->limit(3)
            ->get();
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private function identityFactsForShow(
        Pet $pet,
        string $speciesLabel,
        ?string $breedLabel,
        ?string $sexLabel,
        ?string $ageLabel,
        ?string $lifeStageLabel,
    ): array {
        $facts = [
            [
                'label' => 'Life stage',
                'value' => $lifeStageLabel ?? ($ageLabel ? 'Age known' : 'Not set yet'),
                'tone' => 'warm',
            ],
            [
                'label' => 'Species',
                'value' => $breedLabel ? "{$speciesLabel} · {$breedLabel}" : $speciesLabel,
                'tone' => 'forest',
            ],
        ];

        if ($sexLabel) {
            $facts[] = [
                'label' => 'Sex',
                'value' => $sexLabel,
                'tone' => 'default',
            ];
        }

        if ($pet->size) {
            $facts[] = [
                'label' => 'Size',
                'value' => Str::headline((string) $pet->size),
                'tone' => 'default',
            ];
        }

        if ($ageLabel) {
            $facts[] = [
                'label' => 'Dynamic age',
                'value' => $ageLabel,
                'tone' => 'default',
            ];
        }

        if ($pet->is_deceased) {
            $facts[] = [
                'label' => 'Memorial',
                'value' => 'Rainbow Bridge',
                'tone' => 'warm',
            ];
        }

        return $facts;
    }

    /**
     * @return array<int, array{label: string, value: string, meta: ?string}>
     */
    private function careSnapshotForShow(Pet $pet, bool $isOwner): array
    {
        $snapshot = [];

        if (($pet->adoption_status ?? 'not_listed') !== 'not_listed' || (bool) $pet->is_adoptable) {
            $snapshot[] = [
                'label' => 'Adoption',
                'value' => Str::headline((string) ($pet->adoption_status ?: 'available')),
                'meta' => $pet->adoption_listed_at instanceof CarbonInterface
                    ? 'Listed '.$pet->adoption_listed_at->diffForHumans()
                    : null,
            ];
        }

        if (! $isOwner) {
            return $snapshot;
        }

        $latestWeight = PetHealthLog::query()
            ->forPet($pet)
            ->where('log_type', PetHealthLog::TYPE_WEIGHT)
            ->whereNotNull('weight_kg')
            ->latest('logged_at')
            ->first();

        if ($latestWeight instanceof PetHealthLog) {
            $snapshot[] = [
                'label' => 'Latest weight',
                'value' => number_format((float) $latestWeight->weight_kg, 2).' kg',
                'meta' => $latestWeight->logged_at?->diffForHumans(),
            ];
        }

        $latestVaccination = PetHealthLog::query()
            ->forPet($pet)
            ->whereIn('log_type', [PetHealthLog::TYPE_VACCINATION, PetHealthLog::TYPE_VACCINE_LEGACY])
            ->latest('logged_at')
            ->first();

        if ($latestVaccination instanceof PetHealthLog) {
            $snapshot[] = [
                'label' => 'Vaccination',
                'value' => $latestVaccination->title ?: 'Recorded',
                'meta' => $latestVaccination->logged_at?->diffForHumans(),
            ];
        }

        $nextCare = PetHealthLog::query()
            ->forPet($pet)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '>=', now()->startOfDay())
            ->orderBy('next_due_at')
            ->first();

        if ($nextCare instanceof PetHealthLog) {
            $snapshot[] = [
                'label' => 'Next care',
                'value' => $nextCare->title ?: $nextCare->type_label,
                'meta' => $nextCare->next_due_at?->toFormattedDateString(),
            ];
        }

        return $snapshot;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePersonalityTags(mixed $tags): array
    {
        if ($tags === null) {
            return [];
        }

        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }

        return collect((array) $tags)
            ->map(static fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveSpeciesLabel(Pet $pet): string
    {
        $species = (string) ($pet->species ?? '');

        return $species !== '' ? Str::headline($species) : __('pets.not_available');
    }

    private function resolveBreedLabel(Pet $pet): ?string
    {
        $breed = (string) ($pet->breed ?? '');

        return $breed !== '' ? Str::headline($breed) : null;
    }

    private function resolveSexLabel(Pet $pet): ?string
    {
        $sex = $pet->sex ?? $pet->gender;

        if (! $sex) {
            return null;
        }

        return Str::headline((string) $sex);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildGalleryManagerItems(Pet $pet): Collection
    {
        $galleryItems = collect($pet->getMedia(Pet::MEDIA_COLLECTION_GALLERY))
            ->sortBy(function (Media $media): string {
                $order = (int) ($media->order_column ?? 0);
                $timestamp = (int) (optional($media->created_at)->timestamp ?? 0);

                return sprintf('%05d-%010d', $order, $timestamp);
            })
            ->values();

        $galleryIds = $galleryItems->pluck('id')->values()->all();
        $galleryLastIndex = count($galleryIds) - 1;

        return $galleryItems->map(function (Media $media, int $index) use ($galleryIds, $galleryLastIndex): array {
            $thumbUrl = $media->getUrl(Pet::MEDIA_CONVERSION_GALLERY_THUMB);
            $thumbUrl = $thumbUrl !== '' ? $thumbUrl : $media->getUrl();
            $caption = (string) ($media->getCustomProperty('caption') ?? '');
            $altText = (string) ($media->getCustomProperty('alt_text') ?? '');

            $moveLeft = null;
            $moveRight = null;

            if ($index > 0) {
                $moveLeft = $this->swapGalleryOrder($galleryIds, $index, $index - 1);
            }

            if ($index < $galleryLastIndex) {
                $moveRight = $this->swapGalleryOrder($galleryIds, $index, $index + 1);
            }

            return [
                'id' => $media->getKey(),
                'thumb_url' => $thumbUrl,
                'caption' => $caption,
                'alt_text' => $altText,
                'move_left' => $moveLeft,
                'move_right' => $moveRight,
            ];
        })->values();
    }

    /**
     * @param  array<int, int>  $order
     * @return array<int, int>
     */
    private function swapGalleryOrder(array $order, int $from, int $to): array
    {
        $swapped = $order;

        [$swapped[$from], $swapped[$to]] = [$swapped[$to], $swapped[$from]];

        return $swapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGalleryItem(mixed $item): array
    {
        $url = null;
        $label = __('pets.gallery_item');
        $caption = '';
        $altText = '';

        if (is_object($item) && method_exists($item, 'getUrl')) {
            $url = $item->getUrl(Pet::MEDIA_CONVERSION_GALLERY_MEDIUM) ?: $item->getUrl();
            $label = (string) ($item->name ?? $item->file_name ?? $label);
            $caption = (string) ($item->getCustomProperty('caption') ?? '');
            $altText = (string) ($item->getCustomProperty('alt_text') ?? '');
        }

        $alt = $altText !== '' ? $altText : $label;

        return [
            'id' => data_get($item, 'id'),
            'url' => $url,
            'label' => $label,
            'caption' => $caption,
            'alt' => $alt,
        ];
    }
}
