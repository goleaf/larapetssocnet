<?php

use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('renders the post create page with livewire', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('posts.create'))
        ->assertOk()
        ->assertSeeLivewire('pages::post.create');
});

it('creates a post from the livewire page component', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $pet = Pet::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::post.create')
        ->set('body', 'A Livewire post about #pets')
        ->set('visibility', Post::VISIBILITY_PUBLIC)
        ->set('pet_id', $pet->id)
        ->set('tagged_pets', [$pet->id])
        ->set('location', 'Dog park')
        ->set('media', [UploadedFile::fake()->image('photo.jpg', 800, 600)])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $post = Post::query()->latest('id')->firstOrFail();

    expect($post->type)->toBe(Post::TYPE_PHOTO)
        ->and($post->pet_id)->toBe($pet->id)
        ->and($post->location)->toBe('Dog park')
        ->and($post->getMedia('photos'))->toHaveCount(1);
});

it('rejects mixing photos and video in the livewire page component', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::post.create')
        ->set('media', [
            UploadedFile::fake()->image('photo.jpg', 800, 600),
            UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
        ])
        ->call('save')
        ->assertHasErrors(['media']);

    expect(Post::query()->count())->toBe(0);
});
