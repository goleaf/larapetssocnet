<?php

use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('defaults public marketplace catalog to active listings when status filter is invalid', function (): void {
    MarketplaceListing::factory()->create([
        'status' => MarketplaceListing::STATUS_ACTIVE,
        'title' => 'Active Listing',
    ]);

    MarketplaceListing::factory()->create([
        'status' => MarketplaceListing::STATUS_SOLD,
        'title' => 'Sold Listing',
    ]);

    $response = $this->get(route('marketplace.index', [
        'status' => 'invalid-status',
    ]));

    $response
        ->assertOk()
        ->assertViewIs('marketplace.index')
        ->assertViewHas('status', MarketplaceListing::STATUS_ACTIVE)
        ->assertViewHas('listings', function ($listings): bool {
            $statuses = $listings->getCollection()->pluck('status')->unique()->values()->all();

            return $statuses === [MarketplaceListing::STATUS_ACTIVE];
        });
});

it('applies seller dashboard status filters to authenticated users listings only', function (): void {
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();

    MarketplaceListing::factory()->create([
        'user_id' => $viewer->getKey(),
        'status' => MarketplaceListing::STATUS_DRAFT,
        'title' => 'Viewer Draft Listing',
    ]);

    MarketplaceListing::factory()->create([
        'user_id' => $viewer->getKey(),
        'status' => MarketplaceListing::STATUS_ACTIVE,
        'title' => 'Viewer Active Listing',
    ]);

    MarketplaceListing::factory()->create([
        'user_id' => $otherUser->getKey(),
        'status' => MarketplaceListing::STATUS_DRAFT,
        'title' => 'Other User Draft Listing',
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('marketplace.my-listings', [
            'status' => MarketplaceListing::STATUS_DRAFT,
        ]));

    $response
        ->assertOk()
        ->assertViewIs('marketplace.my-listings')
        ->assertViewHas('listings', function ($listings) use ($viewer): bool {
            return $listings->getCollection()->every(
                fn (MarketplaceListing $listing): bool => (int) $listing->user_id === (int) $viewer->getKey()
                    && $listing->status === MarketplaceListing::STATUS_DRAFT
            );
        });
});

it('can resolve soft deleted listings via model helper when with trashed is enabled', function (): void {
    $listing = MarketplaceListing::factory()->create();
    $listing->delete();

    expect(fn () => MarketplaceListing::findByIdOrFail($listing->getKey()))
        ->toThrow(ModelNotFoundException::class);

    $resolved = MarketplaceListing::findByIdOrFail($listing->getKey(), true);

    expect($resolved->is($listing))->toBeTrue();
    expect($resolved->trashed())->toBeTrue();
});

it('creates listings for a seller through model helper with seller ownership attached', function (): void {
    $seller = User::factory()->create();

    $listing = MarketplaceListing::createForSeller($seller, [
        'title' => 'Model Helper Listing',
        'description' => 'Model helper description.',
        'listing_type' => 'adoption',
        'status' => MarketplaceListing::STATUS_ACTIVE,
        'price' => 120.50,
        'currency' => 'USD',
    ]);

    expect((int) $listing->user_id)->toBe((int) $seller->getKey());
    expect($listing->title)->toBe('Model Helper Listing');
});
