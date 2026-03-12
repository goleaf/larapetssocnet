<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeletePetAction
{
    public function handle(User $actor, Pet $pet): void
    {
        DB::transaction(function () use ($pet): void {
            $pet->loadMissing('owner');

            $this->detachFollowers($pet);
            $this->detachFromPosts($pet);

            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);
            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_COVER);

            if ($pet->owner) {
                $pet->owner->decrementCounter('pets_count');
            }

            if (! $pet->trashed()) {
                $pet->delete();
            }
        });

        $this->logActivity('deleted', $pet, $actor);
    }

    private function detachFollowers(Pet $pet): void
    {
        $followerIds = $pet->followers()
            ->select(['users.id'])
            ->pluck('users.id');

        if ($followerIds->isEmpty()) {
            return;
        }

        User::query()
            ->whereIn('id', $followerIds->all())
            ->select(['id', 'following_pets_count'])
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $user->decrementCounter('following_pets_count');
                }
            });

        $pet->followers()->detach();
    }

    private function detachFromPosts(Pet $pet): void
    {
        Post::query()
            ->where('pet_id', $pet->getKey())
            ->update(['pet_id' => null]);

        Post::query()
            ->select(['id', 'tagged_pets'])
            ->whereNotNull('tagged_pets')
            ->chunkById(200, function (Collection $posts) use ($pet): void {
                $petId = (int) $pet->getKey();

                foreach ($posts as $post) {
                    $tagged = collect($post->tagged_pets ?? [])
                        ->map(static fn (mixed $id): int => (int) $id)
                        ->filter(static fn (int $id): bool => $id > 0)
                        ->unique()
                        ->values();

                    if (! $tagged->contains($petId)) {
                        continue;
                    }

                    $remaining = $tagged
                        ->reject(static fn (int $id): bool => $id === $petId)
                        ->values()
                        ->all();

                    $post->updateQuietly([
                        'tagged_pets' => $remaining === [] ? null : $remaining,
                    ]);
                }
            });
    }

    private function logActivity(string $description, Pet $pet, ?User $actor): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        activity()
            ->causedBy($actor)
            ->performedOn($pet)
            ->log($description);
    }
}
