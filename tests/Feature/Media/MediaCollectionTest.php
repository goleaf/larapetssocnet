<?php

use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\Event;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

it('attaches uploads to the configured collection', function (callable $makeModel, string $collection, callable $makeFile): void {
    $model = $makeModel();
    $file = $makeFile();

    $media = $model->addMedia($file)->toMediaCollection($collection);

    expect($media->collection_name)
        ->toBe($collection)
        ->and($model->getMedia($collection))
        ->toHaveCount(1);
})->with([
    'user avatar' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_AVATAR,
        fn (): UploadedFile => UploadedFile::fake()->image('avatar.jpg', 400, 400),
    ],
    'user cover' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('cover.jpg', 1600, 400),
    ],
    'user legacy photos' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_PHOTOS,
        fn (): UploadedFile => UploadedFile::fake()->image('photos.jpg', 1200, 900),
    ],
    'user attachments' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_ATTACHMENTS,
        fn (): UploadedFile => UploadedFile::fake()->createWithContent('attachment.pdf', 'media pdf fixture'),
    ],
    'pet avatar' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_AVATAR,
        fn (): UploadedFile => UploadedFile::fake()->image('pet-avatar.jpg', 500, 500),
    ],
    'pet cover' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('pet-cover.jpg', 1400, 900),
    ],
    'pet gallery' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_GALLERY,
        fn (): UploadedFile => UploadedFile::fake()->image('pet-gallery.jpg', 1000, 1000),
    ],
    'group avatar' => [
        fn (): Group => Group::factory()->create(),
        Group::MEDIA_COLLECTION_AVATAR,
        fn (): UploadedFile => UploadedFile::fake()->image('group-avatar.jpg', 400, 400),
    ],
    'group cover' => [
        fn (): Group => Group::factory()->create(),
        Group::MEDIA_COLLECTION_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('group-cover.jpg', 1600, 600),
    ],
    'group explicit cover' => [
        fn (): Group => Group::factory()->create(),
        Group::MEDIA_COLLECTION_GROUP_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('group-cover-alias.jpg', 1600, 600),
    ],
    'event cover' => [
        fn (): Event => Event::factory()->create(),
        Event::MEDIA_COLLECTION_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('event-cover.jpg', 1600, 800),
    ],
    'event event cover' => [
        fn (): Event => Event::factory()->create(),
        Event::MEDIA_COLLECTION_EVENT_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('event-cover-main.jpg', 1600, 800),
    ],
    'event gallery' => [
        fn (): Event => Event::factory()->create(),
        Event::MEDIA_COLLECTION_GALLERY,
        fn (): UploadedFile => UploadedFile::fake()->image('event-gallery.jpg', 1200, 900),
    ],
    'contest cover' => [
        fn (): Contest => Contest::create([
            'organizer_user_id' => User::factory()->create()->id,
            'title' => 'Photo Contest',
            'description' => 'Media test',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]),
        Contest::MEDIA_COLLECTION_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('contest-cover.jpg', 1600, 900),
    ],
    'contest entry' => [
        function (): ContestEntry {
            $contest = Contest::create([
                'organizer_user_id' => User::factory()->create()->id,
                'title' => 'Contest Entry Test',
                'description' => 'Media test',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(3),
            ]);

            return ContestEntry::create([
                'contest_id' => $contest->id,
                'user_id' => User::factory()->create()->id,
            ]);
        },
        ContestEntry::MEDIA_COLLECTION_ENTRY_PHOTO,
        fn (): UploadedFile => UploadedFile::fake()->image('contest-entry.jpg', 1100, 1100),
    ],
    'listing cover' => [
        fn (): MarketplaceListing => MarketplaceListing::factory()->create(),
        MarketplaceListing::MEDIA_COLLECTION_COVER,
        fn (): UploadedFile => UploadedFile::fake()->image('listing-cover.jpg', 1600, 900),
    ],
    'listing listing images' => [
        fn (): MarketplaceListing => MarketplaceListing::factory()->create(),
        MarketplaceListing::MEDIA_COLLECTION_LISTING_IMAGES,
        fn (): UploadedFile => UploadedFile::fake()->image('listing-image.jpg', 1100, 900),
    ],
    'listing gallery' => [
        fn (): MarketplaceListing => MarketplaceListing::factory()->create(),
        MarketplaceListing::MEDIA_COLLECTION_GALLERY,
        fn (): UploadedFile => UploadedFile::fake()->image('listing-gallery.jpg', 1100, 900),
    ],
    'post media' => [
        fn (): Post => Post::factory()->create(),
        Post::MEDIA_COLLECTION_POST_MEDIA,
        fn (): UploadedFile => UploadedFile::fake()->image('post-media.jpg', 1200, 900),
    ],
    'post attachments' => [
        fn (): Post => Post::factory()->create(),
        Post::MEDIA_COLLECTION_ATTACHMENTS,
        fn (): UploadedFile => UploadedFile::fake()->createWithContent('post-attachment.pdf', 'media pdf fixture'),
    ],
]);

it('rejects wrong MIME types for image-only media collections', function (callable $makeModel, string $collection): void {
    $model = $makeModel();

    expect(
        fn () => $model->addMedia(UploadedFile::fake()->create('invalid-document.txt', 20, 'text/plain'))
            ->toMediaCollection($collection),
    )->toThrow(FileCannotBeAdded::class);
    expect($model->getMedia($collection))->toHaveCount(0);
})->with([
    'user avatar' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_AVATAR,
    ],
    'pet cover' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_COVER,
    ],
    'group cover' => [
        fn (): Group => Group::factory()->create(),
        Group::MEDIA_COLLECTION_COVER,
    ],
    'event gallery' => [
        fn (): Event => Event::factory()->create(),
        Event::MEDIA_COLLECTION_GALLERY,
    ],
    'contest cover' => [
        fn (): Contest => Contest::create([
            'organizer_user_id' => User::factory()->create()->id,
            'title' => 'Reject MIME Contest',
            'description' => 'media test',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]),
        Contest::MEDIA_COLLECTION_COVER,
    ],
    'post media' => [
        fn (): Post => Post::factory()->create(),
        Post::MEDIA_COLLECTION_POST_MEDIA,
    ],
]);

it('enforces strict attachment allowlists', function (callable $makeModel, string $collection, callable $makeFile, bool $isAccepted): void {
    $model = $makeModel();
    $file = $makeFile();

    if (! $isAccepted) {
        expect(
            fn () => $model->addMedia($file)->toMediaCollection($collection),
        )->toThrow(FileCannotBeAdded::class);
        expect($model->getMedia($collection))->toHaveCount(0);

        return;
    }

    $media = $model->addMedia($file)->toMediaCollection($collection);

    expect($media->collection_name)->toBe($collection)
        ->and($model->getMedia($collection))->toHaveCount(1);
})->with([
    'user attachments accepts pdf' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_ATTACHMENTS,
        fn (): UploadedFile => UploadedFile::fake()->createWithContent('doc.pdf', 'media pdf fixture'),
        true,
    ],
    'user attachments rejects image' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_ATTACHMENTS,
        fn (): UploadedFile => UploadedFile::fake()->image('avatar.jpg', 600, 600),
        false,
    ],
    'post attachments accepts txt' => [
        fn (): Post => Post::factory()->create(),
        Post::MEDIA_COLLECTION_ATTACHMENTS,
        fn (): UploadedFile => UploadedFile::fake()->createWithContent('notes.txt', 'media text fixture'),
        true,
    ],
    'post attachments rejects gif' => [
        fn (): Post => Post::factory()->create(),
        Post::MEDIA_COLLECTION_ATTACHMENTS,
        fn (): UploadedFile => UploadedFile::fake()->create('graphic.svg', 20, 'image/svg+xml'),
        false,
    ],
]);
