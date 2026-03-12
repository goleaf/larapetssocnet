<?php

use App\Actions\Pets\AttachPetToPostAction;
use App\Actions\Pets\DeletePetAction;
use App\Actions\Pets\DetachPetFromPostAction;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use App\Services\SyncPetCountersService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('syncs pet counters from relationships', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'followers_count' => 5,
        'posts_count' => 5,
    ]);

    $follower = User::factory()->create();
    $follower->followPet($pet);

    Post::factory()->for($owner)->create([
        'pet_id' => $pet->id,
    ]);

    $pet->updateQuietly([
        'followers_count' => 0,
        'posts_count' => 0,
    ]);

    $service = app(SyncPetCountersService::class);
    $service->sync($pet);

    $pet->refresh();

    expect($pet->followers_count)->toBe(1)
        ->and($pet->posts_count)->toBe(1);
});

it('attaches and detaches pets on posts via actions', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['posts_count' => 0]);
    $post = Post::factory()->for($owner)->create(['pet_id' => null, 'tagged_pets' => []]);

    $attach = app(AttachPetToPostAction::class);
    $updated = $attach->handle($owner, $post, $pet);

    expect($updated->pet_id)->toBe($pet->id)
        ->and($updated->tagged_pets)->toContain($pet->id)
        ->and($pet->fresh()->posts_count)->toBe(1);

    $detach = app(DetachPetFromPostAction::class);
    $updated = $detach->handle($owner, $post, $pet);

    expect($updated->pet_id)->toBeNull()
        ->and($updated->tagged_pets ?? [])->not()->toContain($pet->id)
        ->and($pet->fresh()->posts_count)->toBe(0);
});

it('deletes pets and cleans up followers', function (): void {
    $owner = User::factory()->create();
    $follower = User::factory()->create(['following_pets_count' => 0]);
    $pet = Pet::factory()->for($owner)->create();

    $follower->followPet($pet);

    $action = app(DeletePetAction::class);
    $action->handle($owner, $pet);

    expect($follower->fresh()->following_pets_count)->toBe(0);
    $this->assertSoftDeleted('pets', ['id' => $pet->id]);
});
