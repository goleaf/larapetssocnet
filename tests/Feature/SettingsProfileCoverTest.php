<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores a cover image from settings profile', function (): void {
    $disk = (string) config('media-library.disk_name');
    Storage::fake($disk);

    $user = User::factory()->create();
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

    expect($user->getFirstMedia(User::MEDIA_COLLECTION_COVER))->not->toBeNull();
});

it('removes a cover image from settings profile', function (): void {
    $disk = (string) config('media-library.disk_name');
    Storage::fake($disk);

    $user = User::factory()->create();
    $user->updateCover(UploadedFile::fake()->image('cover.jpg', 1600, 480)->size(3000));

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

    expect($user->getFirstMedia(User::MEDIA_COLLECTION_COVER))->toBeNull();
});
