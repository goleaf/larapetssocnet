<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttachPetToPostAction
{
    public function handle(User $actor, Post $post, Pet $pet): Post
    {
        Gate::forUser($actor)->authorize('attachPost', [$pet, $post]);

        return DB::transaction(function () use ($post, $pet): Post {
            $currentPetId = $post->pet_id ? (int) $post->pet_id : null;
            $nextPetId = (int) $pet->getKey();

            if ($currentPetId && $currentPetId !== $nextPetId) {
                $previousPet = Pet::query()->whereKey($currentPetId)->first();

                if ($previousPet) {
                    $previousPet->decrementCounter('posts_count');
                }
            }

            if ($currentPetId !== $nextPetId) {
                $post->pet_id = $nextPetId;
            }

            $taggedPets = collect($post->tagged_pets ?? [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            if (! $taggedPets->contains($nextPetId)) {
                $taggedPets->push($nextPetId);
            }

            $post->tagged_pets = $taggedPets->values()->all();
            $post->save();

            if ($currentPetId !== $nextPetId) {
                $pet->incrementCounter('posts_count');
            }

            return $post->refresh();
        });
    }
}
