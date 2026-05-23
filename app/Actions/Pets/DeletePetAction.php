<?php

declare(strict_types=1);

namespace App\Actions\Pets;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use App\Models\Pets\PetMilestone;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeletePetAction
{
    public function handle(User $actor, Pet $pet): void
    {
        DB::transaction(function () use ($pet): void {
            $this->detachFollowers($pet);
            $this->softDeleteAssociatedRecords($pet);

            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);
            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_COVER);

            $owner = $pet->owner()->first();

            if ($owner instanceof User) {
                $owner->decrementCounter('pets_count');
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

        /** @var EloquentBuilder<User> $usersQuery */
        $usersQuery = User::query()->whereIn('id', $followerIds->all());

        $usersQuery->chunkById(200, function (Collection $users): void {
            foreach ($users as $user) {
                $user->decrementCounter('following_pets_count');
            }
        });

        $pet->followers()->detach();
    }

    private function softDeleteAssociatedRecords(Pet $pet): void
    {
        $this->softDeletePosts($pet);
        $this->softDeleteMilestones($pet);
        $this->softDeleteMarketplaceListings($pet);
        $this->detachTaggedPostReferences($pet);
    }

    private function softDeletePosts(Pet $pet): void
    {
        /** @var EloquentBuilder<Post> $postsQuery */
        $postsQuery = Post::query()->where('pet_id', $pet->getKey());

        $postsQuery->chunkById(100, function (Collection $posts): void {
            foreach ($posts as $post) {
                if (! $post->trashed()) {
                    $post->delete();
                }
            }
        });
    }

    private function softDeleteMilestones(Pet $pet): void
    {
        /** @var EloquentBuilder<PetMilestone> $milestonesQuery */
        $milestonesQuery = PetMilestone::query()->where('pet_id', $pet->getKey());

        $milestonesQuery->chunkById(100, function (Collection $milestones): void {
            foreach ($milestones as $milestone) {
                $milestone->delete();
            }
        });
    }

    private function softDeleteMarketplaceListings(Pet $pet): void
    {
        /** @var EloquentBuilder<MarketplaceListing> $listingsQuery */
        $listingsQuery = MarketplaceListing::query()->where('pet_id', $pet->getKey());

        $listingsQuery->chunkById(100, function (Collection $listings): void {
            foreach ($listings as $listing) {
                $listing->delete();
            }
        });
    }

    private function detachTaggedPostReferences(Pet $pet): void
    {
        /** @var EloquentBuilder<Post> $postsQuery */
        $postsQuery = Post::query()->whereNotNull('tagged_pets');

        $postsQuery->chunkById(200, function (Collection $posts) use ($pet): void {
            $petId = (int) $pet->getKey();

            foreach ($posts as $post) {
                $taggedPetIds = $post->getAttribute('tagged_pets');
                $tagged = collect(is_array($taggedPetIds) ? $taggedPetIds : [])
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
