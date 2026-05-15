<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PetAvatarService
{
    public function updateAvatar(User $actor, Pet $pet, UploadedFile $file): void
    {
        DB::transaction(function () use ($pet, $file): void {
            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
            $pet->addMedia($file)->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);

            $pet->forceFill([
                'avatar_path' => null,
            ])->saveQuietly();
        });

        $this->logActivity('avatar_updated', $pet, $actor);
    }

    public function removeAvatar(User $actor, Pet $pet): void
    {
        DB::transaction(function () use ($pet): void {
            $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);

            $pet->forceFill([
                'avatar_path' => null,
            ])->saveQuietly();
        });

        $this->logActivity('avatar_removed', $pet, $actor);
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
