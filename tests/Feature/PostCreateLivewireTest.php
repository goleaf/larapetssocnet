<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

uses(RefreshDatabase::class);

it('renders the post create page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('posts.create'))
        ->assertOk()
        ->assertSee('Create Post')
        ->assertSee('Visibility');
});

it('renders the livewire post create component', function (): void {
    if (! $this->app->providerIsLoaded(LivewireServiceProvider::class)) {
        $this->app->register(LivewireServiceProvider::class);
    }

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::post.create')
        ->assertSee('Create Post')
        ->assertSee('Visibility');
});

it('creates a post from the create form', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $pet = Pet::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('posts.create'))
        ->post(route('posts.store'), [
            'body' => 'A form post about #pets',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'pet_id' => $pet->id,
            'tagged_pets' => [$pet->id],
            'location' => 'Dog park',
            'media' => [UploadedFile::fake()->image('photo.jpg', 800, 600)],
        ]);

    $response->assertRedirect(route('posts.create'));

    $post = Post::query()->latest('id')->firstOrFail();

    expect($post->type)->toBe(Post::TYPE_PHOTO)
        ->and($post->pet_id)->toBe($pet->id)
        ->and($post->location)->toBe('Dog park')
        ->and($post->getMedia('photos'))->toHaveCount(1);
});

it('rejects mixing photos and video in the create form', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('posts.create'))
        ->post(route('posts.store'), [
            'body' => 'Invalid media payload',
            'media' => [
                UploadedFile::fake()->image('photo.jpg', 800, 600),
                UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
            ],
        ]);

    $response
        ->assertRedirect(route('posts.create'))
        ->assertSessionHasErrors(['media']);

    expect(Post::query()->count())->toBe(0);
});
