<?php

use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\Event;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

it('replaces single-file media and removes the replaced record for every single-file collection', function (callable $makeModel, string $collection): void {
    $model = $makeModel();

    $first = $model
        ->addMedia(UploadedFile::fake()->image('first.jpg', 900, 900))
        ->toMediaCollection($collection);

    $oldPath = $first->getPathRelativeToRoot();

    $model->addMedia(UploadedFile::fake()->image('second.jpg', 900, 900))->toMediaCollection($collection);

    expect($model->getMedia($collection))->toHaveCount(1);
    $this->assertDatabaseMissing('media', ['id' => $first->id]);
    Storage::disk('public')->assertMissing($oldPath);
})->with([
    'user avatar' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_AVATAR,
    ],
    'user cover' => [
        fn (): User => User::factory()->create(),
        User::MEDIA_COLLECTION_COVER,
    ],
    'pet avatar' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_AVATAR,
    ],
    'pet cover' => [
        fn (): Pet => Pet::factory()->create(),
        Pet::MEDIA_COLLECTION_COVER,
    ],
    'group cover' => [
        fn (): Group => Group::factory()->create(),
        Group::MEDIA_COLLECTION_COVER,
    ],
    'group explicit cover' => [
        fn (): Group => Group::factory()->create(),
        Group::MEDIA_COLLECTION_GROUP_COVER,
    ],
    'event cover' => [
        fn (): Event => Event::factory()->create(),
        Event::MEDIA_COLLECTION_COVER,
    ],
    'event event cover' => [
        fn (): Event => Event::factory()->create(),
        Event::MEDIA_COLLECTION_EVENT_COVER,
    ],
    'contest cover' => [
        function (): Contest {
            return Contest::create([
                'organizer_user_id' => User::factory()->create()->id,
                'title' => 'Replacement Contest',
                'description' => 'Media replacement test',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(3),
            ]);
        },
        Contest::MEDIA_COLLECTION_COVER,
    ],
    'contest entry' => [
        function (): ContestEntry {
            $contest = Contest::create([
                'organizer_user_id' => User::factory()->create()->id,
                'title' => 'Replacement Entry Contest',
                'description' => 'Media replacement test',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(3),
            ]);

            return ContestEntry::create([
                'contest_id' => $contest->id,
                'user_id' => User::factory()->create()->id,
            ]);
        },
        ContestEntry::MEDIA_COLLECTION_ENTRY,
    ],
    'listing cover' => [
        fn (): MarketplaceListing => MarketplaceListing::factory()->create(),
        MarketplaceListing::MEDIA_COLLECTION_COVER,
    ],
]);
