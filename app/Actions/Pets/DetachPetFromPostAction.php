<?php

namespace App\Actions\Pets;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class DetachPetFromPostAction
{
    public function handle(User $actor, Post $post, Pet $pet): Post
    {
        Gate::forUser($actor)->authorize('detachPost', [$pet, $post]);

        return DB::transaction(function () use ($post, $pet): Post {
            $petId = (int) $pet->getKey();

            if ((int) ($post->getAttribute('pet_id') ?? 0) === $petId) {
                $post->setAttribute('pet_id', null);
                $pet->decrementCounter('posts_count');
            }

            $taggedPetIds = $post->getAttribute('tagged_pets');
            $taggedPets = collect(is_array($taggedPetIds) ? $taggedPetIds : [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0 && $id !== $petId)
                ->unique()
                ->values();

            $post->setAttribute('tagged_pets', $taggedPets->isEmpty() ? null : $taggedPets->all());

            if ($post->isDirty()) {
                $post->save();
            }

            if (Schema::hasTable('pet_post')) {
                $post->pets()->detach($petId);
            }

            return $post->refresh();
        });
    }
}
