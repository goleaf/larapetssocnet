<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores a cover image from settings profile', function (): void {
    $disk = (string) config('media-library.disk_name');
    Storage::fake($disk);

    $user = User::factory()->create([
        'cover_photo_position' => 83.5,
    ]);
    $file = UploadedFile::fake()->image('cover.jpg', 1600, 480)->size(3000);

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'cover' => $file,
        ])
        ->assertRedirect('/settings/profile')
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->getFirstMedia(User::MEDIA_COLLECTION_COVER))->not->toBeNull()
        ->and((float) $user->cover_photo_position)->toBe(User::DEFAULT_COVER_PHOTO_POSITION);
});

it('removes a cover image from settings profile', function (): void {
    $disk = (string) config('media-library.disk_name');
    Storage::fake($disk);

    $user = User::factory()->create();
    $user->updateCover(UploadedFile::fake()->image('cover.jpg', 1600, 480)->size(3000));
    $user->forceFill(['cover_photo_position' => 91.25])->saveQuietly();

    expect($user->getFirstMedia(User::MEDIA_COLLECTION_COVER))->not->toBeNull();

    $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'remove_cover' => '1',
        ])
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->getFirstMedia(User::MEDIA_COLLECTION_COVER))->toBeNull()
        ->and((float) $user->cover_photo_position)->toBe(User::DEFAULT_COVER_PHOTO_POSITION);
});
