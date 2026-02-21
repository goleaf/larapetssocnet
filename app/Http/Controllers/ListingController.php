<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceListing;
use App\Services\ListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function __construct(private readonly ListingService $listingService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MarketplaceListing::class);

        $viewer = $request->user();
        $status = $request->string('status')->trim()->lower()->value();
        $sort = $request->string('sort')->trim()->value() ?: 'newest';

        $query = MarketplaceListing::query()
            ->withTrashed()
            ->where('user_id', $viewer->getKey())
            ->with(['pet:id,name'])
            ->search(trim((string) $request->input('q')));

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'most_viewed' => $query->orderByDesc('views_count'),
            default => $query->latest('created_at'),
        };

        return view('marketplace.my-listings', [
            'listings' => $query->paginate(12)->withQueryString(),
            'status' => $status === '' ? 'all' : $status,
            'sort' => $sort,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', MarketplaceListing::class);

        return view('marketplace.create', [
            'listing' => new MarketplaceListing([
                'status' => MarketplaceListing::STATUS_ACTIVE,
                'currency' => 'USD',
                'listing_type' => 'adoption',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MarketplaceListing::class);

        $validated = $request->validate($this->rules($request, isUpdate: false));
        $payload = $this->buildPayload($request, $validated, isUpdate: false);

        $this->listingService->create($request->user(), $payload);

        return redirect()
            ->route('listings.index')
            ->with('success', 'Listing created successfully.');
    }

    public function edit(Request $request, int $listing): View
    {
        $marketplaceListing = $this->findListing($listing);
        $this->authorize('update', $marketplaceListing);

        return view('marketplace.edit', [
            'listing' => $marketplaceListing,
            'gallery' => $marketplaceListing->getMedia('gallery')->merge($marketplaceListing->getMedia('images')),
        ]);
    }

    public function update(Request $request, int $listing): RedirectResponse
    {
        $marketplaceListing = $this->findListing($listing);
        $this->authorize('update', $marketplaceListing);

        $validated = $request->validate($this->rules($request, isUpdate: true));
        $payload = $this->buildPayload($request, $validated, isUpdate: true);

        $this->listingService->update($marketplaceListing, $payload);

        return redirect()
            ->route('listings.index')
            ->with('success', 'Listing updated successfully.');
    }

    public function status(Request $request, int $listing): JsonResponse|RedirectResponse
    {
        $marketplaceListing = $this->findListing($listing, withTrashed: true);
        $this->authorize('update', $marketplaceListing);

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    MarketplaceListing::STATUS_DRAFT,
                    MarketplaceListing::STATUS_ACTIVE,
                    MarketplaceListing::STATUS_SOLD,
                    MarketplaceListing::STATUS_ARCHIVED,
                ]),
            ],
        ]);

        $updated = $this->listingService->changeStatus($marketplaceListing, (string) $validated['status']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Listing status updated.',
                'status' => $updated->status,
            ]);
        }

        return redirect()->back()->with('success', 'Listing status updated.');
    }

    public function destroy(Request $request, int $listing): RedirectResponse
    {
        $marketplaceListing = $this->findListing($listing, withTrashed: true);
        $this->authorize('delete', $marketplaceListing);

        if (! $marketplaceListing->trashed()) {
            $this->listingService->softDelete($marketplaceListing);
        }

        return redirect()
            ->route('listings.index')
            ->with('success', 'Listing deleted.');
    }

    public function restore(Request $request, int $listing): RedirectResponse
    {
        $marketplaceListing = $this->findListing($listing, withTrashed: true);
        $this->authorize('restore', $marketplaceListing);

        $this->listingService->restore($marketplaceListing);

        return redirect()
            ->route('listings.index')
            ->with('success', 'Listing restored.');
    }

    public function deleteImage(Request $request, int $listing, int $image): JsonResponse|RedirectResponse
    {
        $marketplaceListing = $this->findListing($listing, withTrashed: true);
        $this->authorize('update', $marketplaceListing);

        $this->listingService->deleteImage($marketplaceListing, $image);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Image deleted.',
            ]);
        }

        return redirect()->back()->with('success', 'Image deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Request $request, array $validated, bool $isUpdate): array
    {
        $payload = $validated;

        $coverImage = $request->file('cover_image');
        $galleryImages = $request->file('gallery_images', []);

        /** @var list<UploadedFile> $images */
        $images = array_values(array_filter((array) ($validated['images'] ?? []), static fn (mixed $file): bool => $file instanceof UploadedFile));

        if ($coverImage instanceof UploadedFile) {
            array_unshift($images, $coverImage);
            $payload['cover_image_index'] = 0;
        }

        foreach ((array) $galleryImages as $galleryImage) {
            if ($galleryImage instanceof UploadedFile) {
                $images[] = $galleryImage;
            }
        }

        $payload['images'] = $images;

        if (! $isUpdate) {
            unset($payload['replace_gallery'], $payload['remove_cover_image'], $payload['cover_image_id']);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $isUpdate): array
    {
        $statusRule = Rule::in([
            MarketplaceListing::STATUS_DRAFT,
            MarketplaceListing::STATUS_ACTIVE,
            MarketplaceListing::STATUS_SOLD,
            MarketplaceListing::STATUS_ARCHIVED,
        ]);

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:140'],
            'description' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:5000'],
            'pet_id' => [
                'nullable',
                'integer',
                Rule::exists('pets', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->getKey())),
            ],
            'listing_type' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:32'],
            'status' => [$isUpdate ? 'sometimes' : 'required', 'string', $statusRule],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'max:8192'],
            'cover_image' => ['nullable', 'image', 'max:8192'],
            'gallery_images' => ['nullable', 'array', 'max:8'],
            'gallery_images.*' => ['image', 'max:8192'],
            'cover_image_index' => ['nullable', 'integer', 'min:0', 'max:7'],
            'cover_image_id' => [$isUpdate ? 'nullable' : 'prohibited', 'integer'],
            'replace_gallery' => [$isUpdate ? 'nullable' : 'prohibited', 'boolean'],
            'remove_cover_image' => [$isUpdate ? 'nullable' : 'prohibited', 'boolean'],
        ];
    }

    private function findListing(int $listingId, bool $withTrashed = false): MarketplaceListing
    {
        $query = MarketplaceListing::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($listingId);
    }
}
