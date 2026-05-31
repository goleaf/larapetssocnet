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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

it('removes all attached media when media-bearing models are force deleted', function (callable $makeModel, array $collections): void {
    $model = $makeModel();
    $mediaRecords = [];

    foreach ($collections as $collection) {
        $mediaRecords[] = $model->addMedia(UploadedFile::fake()->image("{$collection}.jpg", 1200, 900))
            ->toMediaCollection((string) $collection);
    }

    $model->forceDelete();

    foreach ($mediaRecords as $media) {
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
    }
})->with([
    'user profile media' => [
        fn (): User => User::factory()->create(),
        [User::MEDIA_COLLECTION_AVATAR, User::MEDIA_COLLECTION_COVER],
    ],
    'pet avatar' => [
        fn (): Pet => Pet::factory()->create(),
        [Pet::MEDIA_COLLECTION_AVATAR],
    ],
    'group cover' => [
        fn (): Group => Group::factory()->create(),
        [Group::MEDIA_COLLECTION_COVER],
    ],
    'event event cover' => [
        fn (): Event => Event::factory()->create(),
        [Event::MEDIA_COLLECTION_EVENT_COVER],
    ],
    'contest cover' => [
        function (): Contest {
            return Contest::create([
                'organizer_user_id' => User::factory()->create()->id,
                'title' => 'Deletion Contest',
                'description' => 'Media deletion test',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(3),
            ]);
        },
        [Contest::MEDIA_COLLECTION_COVER],
    ],
    'contest entry' => [
        function (): ContestEntry {
            $contest = Contest::create([
                'organizer_user_id' => User::factory()->create()->id,
                'title' => 'Deletion Entry Contest',
                'description' => 'Media deletion test',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(3),
            ]);

            return ContestEntry::create([
                'contest_id' => $contest->id,
                'user_id' => User::factory()->create()->id,
            ]);
        },
        [ContestEntry::MEDIA_COLLECTION_ENTRY_PHOTO],
    ],
    'listing cover' => [
        fn (): MarketplaceListing => MarketplaceListing::factory()->create(),
        [MarketplaceListing::MEDIA_COLLECTION_COVER],
    ],
    'post legacy media' => [
        fn (): Post => Post::factory()->create(),
        [Post::MEDIA_COLLECTION_PHOTOS],
    ],
]);

it('renders media lists without issuing per-item media queries', function (): void {
    $user = User::factory()->create();
    $posts = Post::factory()->count(5)->create(['user_id' => $user->id]);

    $posts->each(function (Post $post): void {
        $post->addMedia(UploadedFile::fake()->image("post-{$post->id}.jpg", 600, 600))
            ->toMediaCollection(Post::MEDIA_COLLECTION_PHOTOS);
    });

    DB::enableQueryLog();

    $feedPosts = Post::query()
        ->with('media')
        ->whereIn('id', $posts->modelKeys())
        ->orderBy('id')
        ->get();

    $feedPosts->each(fn (Post $post) => $post->mediaItemsForDisplay());

    $mediaQueries = array_values(array_filter(
        DB::getQueryLog(),
        fn (array $query): bool => str_contains((string) $query['query'], '"media"')
    ));

    DB::disableQueryLog();

    expect(count($mediaQueries))->toBeLessThanOrEqual(2);
});
