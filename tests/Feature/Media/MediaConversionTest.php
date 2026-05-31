<?php

use App\Models\Activities\Contest;
use App\Models\Activities\Event;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

it('builds the configured media conversion set', function (callable $makeModel, string $collection, array $expectedConversions): void {
    $model = $makeModel();
    $media = $model->addMedia(UploadedFile::fake()->image('source.jpg', 1600, 900))->toMediaCollection($collection);

    /** @var Media $media */
    $media->refresh();

    foreach ($expectedConversions as $conversion) {
        expect($media->hasGeneratedConversion($conversion))->toBeTrue();
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot($conversion));
        expect($media->getUrl($conversion))->toContain('/conversions/');
    }
})->with([
    'user cover conversion set' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_COVER,
        [User::MEDIA_CONVERSION_COVER],
    ],
    'user avatar conversion set' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_AVATAR,
        [
            User::MEDIA_CONVERSION_THUMB,
            User::MEDIA_CONVERSION_AVATAR,
            User::MEDIA_CONVERSION_CARD,
            User::MEDIA_CONVERSION_PREVIEW,
            User::MEDIA_CONVERSION_LARGE,
        ],
    ],
    'pet cover conversion set' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_COVER,
        [Pet::MEDIA_CONVERSION_PREVIEW, Pet::MEDIA_CONVERSION_LARGE, Pet::MEDIA_CONVERSION_COVER],
    ],
    'pet avatar conversion set' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_AVATAR,
        [
            Pet::MEDIA_CONVERSION_THUMB,
            Pet::MEDIA_CONVERSION_AVATAR,
            Pet::MEDIA_CONVERSION_CARD,
            Pet::MEDIA_CONVERSION_PREVIEW,
            Pet::MEDIA_CONVERSION_LARGE,
        ],
    ],
    'group cover conversion set' => [
        fn (): Group => Group::factory()->create(),
        Group::MEDIA_COLLECTION_COVER,
        [
            Group::MEDIA_CONVERSION_THUMB,
            Group::MEDIA_CONVERSION_AVATAR,
            Group::MEDIA_CONVERSION_CARD,
            Group::MEDIA_CONVERSION_PREVIEW,
            Group::MEDIA_CONVERSION_LARGE,
            Group::MEDIA_CONVERSION_COVER,
        ],
    ],
    'event event-cover conversion set' => [
        fn (): Event => Event::factory()->create(),
        Event::MEDIA_COLLECTION_EVENT_COVER,
        [
            Event::MEDIA_CONVERSION_THUMB,
            Event::MEDIA_CONVERSION_AVATAR,
            Event::MEDIA_CONVERSION_CARD,
            Event::MEDIA_CONVERSION_PREVIEW,
            Event::MEDIA_CONVERSION_LARGE,
            Event::MEDIA_CONVERSION_COVER,
        ],
    ],
    'contest cover conversion set' => [
        function (): Contest {
            return Contest::create([
                'organizer_user_id' => User::factory()->create()->id,
                'title' => 'Conversion Contest',
                'description' => 'Media conversion test',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(3),
            ]);
        },
        Contest::MEDIA_COLLECTION_COVER,
        [
            Contest::MEDIA_CONVERSION_THUMB,
            Contest::MEDIA_CONVERSION_AVATAR,
            Contest::MEDIA_CONVERSION_CARD,
            Contest::MEDIA_CONVERSION_PREVIEW,
            Contest::MEDIA_CONVERSION_LARGE,
            Contest::MEDIA_CONVERSION_COVER,
        ],
    ],
    'listing gallery conversion set' => [
        fn (): MarketplaceListing => MarketplaceListing::factory()->create(),
        MarketplaceListing::MEDIA_COLLECTION_GALLERY,
        [
            MarketplaceListing::MEDIA_CONVERSION_THUMB,
            MarketplaceListing::MEDIA_CONVERSION_AVATAR,
            MarketplaceListing::MEDIA_CONVERSION_CARD,
            MarketplaceListing::MEDIA_CONVERSION_PREVIEW,
            MarketplaceListing::MEDIA_CONVERSION_LARGE,
            MarketplaceListing::MEDIA_CONVERSION_COVER,
        ],
    ],
    'post media conversion set' => [
        fn (): Post => Post::factory()->create(),
        Post::MEDIA_COLLECTION_POST_MEDIA,
        [
            Post::MEDIA_CONVERSION_THUMB,
            Post::MEDIA_CONVERSION_AVATAR,
            Post::MEDIA_CONVERSION_CARD,
            Post::MEDIA_CONVERSION_PREVIEW,
            Post::MEDIA_CONVERSION_LARGE,
            Post::MEDIA_CONVERSION_COVER,
        ],
    ],
]);

it('queues heavy conversions when conversion queueing is enabled', function (): void {
    Storage::fake('public');
    Queue::fake();
    config()->set('media-library.queue_conversions_by_default', true);

    $user = User::factory()->create();
    $user->addMedia(UploadedFile::fake()->image('queued.jpg', 1600, 900))->toMediaCollection(User::MEDIA_COLLECTION_AVATAR);

    Queue::assertPushed(PerformConversionsJob::class);
});
