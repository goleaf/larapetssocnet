<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DetachPetFromPostAction
{
    public function handle(User $actor, Post $post, Pet $pet): Post
    {
        Gate::forUser($actor)->authorize('detachPost', [$pet, $post]);

        return DB::transaction(function () use ($post, $pet): Post {
            $petId = (int) $pet->getKey();

            if ((int) ($post->pet_id ?? 0) === $petId) {
                $post->pet_id = null;
                $pet->decrementCounter('posts_count');
            }

            $taggedPets = collect($post->tagged_pets ?? [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0 && $id !== $petId)
                ->unique()
                ->values();

            $post->tagged_pets = $taggedPets->isEmpty() ? null : $taggedPets->all();

            if ($post->isDirty()) {
                $post->save();
            }

            return $post->refresh();
        });
    }
}
