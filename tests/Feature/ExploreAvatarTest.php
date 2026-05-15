<?php

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('explore shows author avatar from media library', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'avatar_path' => null,
        'profile_photo_path' => null,
    ]);

    $user->addMedia(UploadedFile::fake()->image('avatar.jpg', 300, 300))
        ->toMediaCollection(User::MEDIA_COLLECTION_AVATAR);

    Post::factory()
        ->for($user)
        ->create([
            'status' => PostStatus::Published->value,
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

    $avatarUrl = $user->fresh()->avatar_url;

    expect($avatarUrl)->not()->toBeEmpty();

    $this->get(route('explore.index'))
        ->assertOk()
        ->assertSee($avatarUrl, false);
});
