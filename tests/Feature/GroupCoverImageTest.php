<?php

use App\Models\Groups\Group;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('owner can upload replace and remove group cover', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->post(route('groups.cover.store', $group->slug), [
            'cover' => UploadedFile::fake()->image('cover-1.jpg', 1200, 600),
        ])
        ->assertRedirect();

    $group->refresh();
    expect($group->getMedia(Group::MEDIA_COLLECTION_COVER))->toHaveCount(1);

    $firstMediaId = $group->getFirstMedia(Group::MEDIA_COLLECTION_COVER)?->getKey();

    $this->actingAs($owner)
        ->post(route('groups.cover.store', $group->slug), [
            'cover' => UploadedFile::fake()->image('cover-2.jpg', 1200, 600),
        ])
        ->assertRedirect();

    $group->refresh();
    expect($group->getMedia(Group::MEDIA_COLLECTION_COVER))->toHaveCount(1);
    expect($group->getFirstMedia(Group::MEDIA_COLLECTION_COVER)?->getKey())->not->toBe($firstMediaId);

    $this->actingAs($owner)
        ->delete(route('groups.cover.destroy', $group->slug))
        ->assertRedirect();

    $group->refresh();
    expect($group->getMedia(Group::MEDIA_COLLECTION_COVER))->toHaveCount(0);
});
