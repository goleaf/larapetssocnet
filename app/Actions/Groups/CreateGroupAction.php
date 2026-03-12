<?php

namespace App\Actions\Groups;

use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\ContentService;
use App\Services\GroupCoverImageService;
use App\Services\GroupSlugService;
use App\Services\SyncGroupCountersService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateGroupAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly GroupSlugService $slugs,
        private readonly GroupCoverImageService $covers,
        private readonly SyncGroupCountersService $counters,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $owner, array $data, ?UploadedFile $avatar = null, ?UploadedFile $cover = null): Group
    {
        return DB::transaction(function () use ($owner, $data, $avatar, $cover): Group {
            $name = $this->normalizeRequiredString($data['name'] ?? null);
            $description = $this->normalizeNullableString($data['description'] ?? null);
            $rules = $this->normalizeNullableString($data['rules'] ?? null);
            $privacy = (string) ($data['privacy'] ?? 'public');
            $slugSeed = $this->normalizeNullableString($data['slug'] ?? null) ?? $name;

            $group = Group::query()->create([
                'owner_user_id' => $owner->getKey(),
                'owner_id' => $owner->getKey(),
                'name' => $name,
                'slug' => $this->slugs->generateUnique($slugSeed),
                'description' => $description,
                'description_html' => $description ? $this->content->process($description) : null,
                'rules' => $rules,
                'privacy' => $privacy,
                'type' => $privacy,
                'location' => $this->normalizeNullableString($data['location'] ?? null),
                'website' => $this->normalizeNullableString($data['website'] ?? null),
                'species_focus' => $data['species_focus'] ?? null,
                'species' => $data['species'] ?? null,
            ]);

            GroupMember::query()->updateOrCreate(
                [
                    'group_id' => $group->getKey(),
                    'user_id' => $owner->getKey(),
                ],
                [
                    'role' => GroupMemberRole::Owner->value,
                    'status' => GroupMemberStatus::Active->value,
                    'joined_at' => now(),
                ]
            );

            if ($avatar instanceof UploadedFile) {
                $group->clearMediaCollection(Group::MEDIA_COLLECTION_AVATAR);
                $group->addMedia($avatar)->toMediaCollection(Group::MEDIA_COLLECTION_AVATAR, 'public');
            }

            if ($cover instanceof UploadedFile) {
                $this->covers->updateCover($owner, $group, $cover);
            }

            $this->counters->syncMembersCount($group);

            return $group->refresh();
        });
    }

    private function normalizeRequiredString(mixed $value): string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? 'Untitled Group' : $normalized;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
