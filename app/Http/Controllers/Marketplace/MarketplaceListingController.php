<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketplaceListingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->trim()->lower()->value();
        $sort = $request->string('sort')->trim()->value() ?: 'newest';

        $listings = MarketplaceListing::paginatePublicCatalog([
            'q' => trim((string) $request->input('q')),
            'listing_type' => $request->string('listing_type')->trim()->value(),
            'status' => $status,
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'location' => trim((string) $request->input('location')),
            'sort' => $sort,
        ]);

        if (! in_array($status, [MarketplaceListing::STATUS_ACTIVE, MarketplaceListing::STATUS_SOLD], true)) {
            $status = MarketplaceListing::STATUS_ACTIVE;
        }

        return view('marketplace.index', [
            'listings' => $listings,
            'status' => $status,
            'sort' => $sort,
            'typeOptions' => MarketplaceListing::listingTypeOptions(),
        ]);
    }

    public function show(Request $request, MarketplaceListing $marketplaceListing): View
    {
        $viewer = $request->user();
        $listing = $marketplaceListing->load([
            'seller:id,name,username,is_private,avatar_path',
            'pet:id,name,species,avatar_path',
        ]);

        if (! $listing->isActive() && (! $viewer || (int) $viewer->getKey() !== (int) $listing->user_id)) {
            abort(404);
        }

        $listing->bumpViews();
        $listing->refresh();

        $restriction = null;

        if ($viewer && (int) $viewer->getKey() !== (int) $listing->user_id) {
            $restriction = $this->messageRestriction($viewer, $listing->seller);
        }

        return view('marketplace.show', [
            'listing' => $listing,
            'gallery' => $listing->getMedia('gallery')->merge($listing->getMedia('images')),
            'canManage' => $viewer && (int) $viewer->getKey() === (int) $listing->user_id,
            'canContactSeller' => $viewer && (int) $viewer->getKey() !== (int) $listing->user_id && $restriction === null,
            'contactRestriction' => $restriction,
        ]);
    }

    public function create(Request $request): View
    {
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
        $viewer = $request->user();
        $validated = $this->validateListing($request, $viewer);

        $listing = DB::transaction(function () use ($viewer, $validated, $request): MarketplaceListing {
            $listing = MarketplaceListing::createForSeller($viewer, $validated);

            $this->syncMediaFromRequest($listing, $request);

            return $listing;
        });

        return redirect()
            ->route('marketplace.show', $listing)
            ->with('success', 'Listing created successfully.');
    }

    public function edit(Request $request, MarketplaceListing $marketplaceListing): View
    {
        abort_unless((int) $marketplaceListing->user_id === (int) $request->user()->getKey(), 403);

        return view('marketplace.edit', [
            'listing' => $marketplaceListing,
            'gallery' => $marketplaceListing->getMedia('gallery')->merge($marketplaceListing->getMedia('images')),
        ]);
    }

    public function update(Request $request, MarketplaceListing $marketplaceListing): RedirectResponse
    {
        abort_unless((int) $marketplaceListing->user_id === (int) $request->user()->getKey(), 403);

        $validated = $this->validateListing($request, $request->user(), isUpdate: true);

        DB::transaction(function () use ($marketplaceListing, $validated, $request): void {
            $marketplaceListing->update($validated);
            $this->syncMediaFromRequest($marketplaceListing, $request, isUpdate: true);
        });

        return redirect()
            ->route('marketplace.show', $marketplaceListing)
            ->with('success', 'Listing updated successfully.');
    }

    public function destroy(Request $request, MarketplaceListing $marketplaceListing): RedirectResponse
    {
        abort_unless((int) $marketplaceListing->user_id === (int) $request->user()->getKey(), 403);

        $marketplaceListing->delete();

        return redirect()
            ->route('marketplace.my-listings')
            ->with('success', 'Listing deleted.');
    }

    public function myListings(Request $request): View
    {
        $viewer = $request->user();
        $status = $request->string('status')->trim()->lower()->value();
        $sort = $request->string('sort')->trim()->value() ?: 'newest';

        return view('marketplace.my-listings', [
            'listings' => MarketplaceListing::paginateForSellerDashboard($viewer, [
                'q' => trim((string) $request->input('q')),
                'status' => $status,
                'sort' => $sort,
            ]),
            'status' => $status,
            'sort' => $sort,
        ]);
    }

    public function contactSeller(Request $request, MarketplaceListing $marketplaceListing): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        $seller = $marketplaceListing->seller;

        abort_unless($seller !== null, 404);

        $restriction = $this->messageRestriction($viewer, $seller);

        if ($restriction !== null) {
            return $this->denyMessaging($request, $restriction, 422);
        }

        $conversationUrl = route('messages.conversation', [
            'peer' => $seller,
            'listing' => $marketplaceListing->getKey(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Conversation is ready.',
                'conversation_url' => $conversationUrl,
            ]);
        }

        return redirect($conversationUrl);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateListing(Request $request, User $viewer, bool $isUpdate = false): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'description' => ['required', 'string', 'max:5000'],
            'pet_id' => [
                'nullable',
                'integer',
                Rule::exists('pets', 'id')->where(fn ($query) => $query->where('user_id', $viewer->getKey())),
            ],
            'listing_type' => ['required', 'string', 'max:32'],
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
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'cover_image' => ['nullable', 'image', 'max:8192'],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['image', 'max:8192'],
            'replace_gallery' => [$isUpdate ? 'nullable' : 'prohibited', 'boolean'],
            'remove_cover_image' => [$isUpdate ? 'nullable' : 'prohibited', 'boolean'],
        ]);

        $validated['currency'] = strtoupper((string) ($validated['currency'] ?? 'USD'));

        return $validated;
    }

    private function syncMediaFromRequest(MarketplaceListing $listing, Request $request, bool $isUpdate = false): void
    {
        if ($isUpdate && $request->boolean('remove_cover_image')) {
            $listing->clearMediaCollection('cover');
        }

        if ($request->hasFile('cover_image')) {
            $listing->clearMediaCollection('cover');
            $listing->addMedia($request->file('cover_image'))->toMediaCollection('cover');
        }

        if ($request->hasFile('gallery_images')) {
            if ($isUpdate && $request->boolean('replace_gallery')) {
                $listing->clearMediaCollection('gallery');
            }

            foreach ((array) $request->file('gallery_images') as $image) {
                $listing->addMedia($image)->toMediaCollection('gallery');
            }
        }
    }

    private function messageRestriction(User $sender, User $recipient): ?string
    {
        if ((int) $sender->getKey() === (int) $recipient->getKey()) {
            return 'You cannot message yourself.';
        }

        if ($this->isBlockedBetween($sender, $recipient)) {
            return 'Messaging is unavailable because one user has blocked the other.';
        }

        if (! $sender->hasAnyRole(['admin', 'moderator']) && (! $this->isFollowing($sender, $recipient) || ! $this->isFollowing($recipient, $sender))) {
            return 'Messaging is available only when both users follow each other.';
        }

        return null;
    }

    private function isBlockedBetween(User $first, User $second): bool
    {
        return $first->hasBlockingRelationshipWith($second);
    }

    private function isFollowing(User $follower, User $followed): bool
    {
        return $follower->isFollowing($followed);
    }

    private function denyMessaging(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->back()->withErrors(['message' => $message]);
    }
}
