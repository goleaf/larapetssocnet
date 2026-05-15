<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Groups\Group;
use App\Models\Identity\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupCoverImageService
{
    public function updateCover(User $actor, Group $group, UploadedFile $file): void
    {
        DB::transaction(function () use ($group, $file): void {
            $group->clearMediaCollection(Group::MEDIA_COLLECTION_COVER);
            $group->addMedia($file)->toMediaCollection(Group::MEDIA_COLLECTION_COVER, 'public');

            $group->forceFill([
                'cover_image' => null,
                'cover_image_path' => null,
            ])->saveQuietly();
        });

        $this->logActivity('cover_updated', $group, $actor);
    }

    public function removeCover(User $actor, Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $group->clearMediaCollection(Group::MEDIA_COLLECTION_COVER);
            $group->forceFill([
                'cover_image' => null,
                'cover_image_path' => null,
            ])->saveQuietly();
        });

        $this->logActivity('cover_removed', $group, $actor);
    }

    private function logActivity(string $description, Group $group, ?User $actor): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        activity()
            ->causedBy($actor)
            ->performedOn($group)
            ->log($description);
    }
}
