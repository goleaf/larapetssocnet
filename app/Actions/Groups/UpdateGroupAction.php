<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\ContentService;
use App\Services\GroupCoverImageService;
use App\Services\GroupSlugService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateGroupAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly GroupSlugService $slugs,
        private readonly GroupCoverImageService $covers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Group $group, array $data, ?UploadedFile $avatar = null, ?UploadedFile $cover = null): Group
    {
        return DB::transaction(function () use ($actor, $group, $data, $avatar, $cover): Group {
            $description = $this->normalizeNullableString($data['description'] ?? $group->description);
            $rules = $this->normalizeNullableString($data['rules'] ?? $group->rules);

            $updates = [
                'name' => $this->normalizeRequiredString($data['name'] ?? $group->name),
                'description' => $description,
                'description_html' => $description ? $this->content->process($description) : null,
                'rules' => $rules,
                'privacy' => $data['privacy'] ?? $group->privacy,
                'type' => $data['privacy'] ?? $group->type,
                'location' => $this->normalizeNullableString($data['location'] ?? $group->location),
                'website' => $this->normalizeNullableString($data['website'] ?? $group->website),
                'species_focus' => $data['species_focus'] ?? $group->species_focus,
                'species' => $data['species'] ?? $group->species,
            ];

            if (array_key_exists('slug', $data) && $data['slug']) {
                $updates['slug'] = $this->slugs->generateUnique((string) $data['slug'], (int) $group->getKey());
            }

            $group->fill($updates)->save();

            if ($avatar instanceof UploadedFile) {
                $group->clearMediaCollection(Group::MEDIA_COLLECTION_AVATAR);
                $group->addMedia($avatar)->toMediaCollection(Group::MEDIA_COLLECTION_AVATAR, 'public');
            }

            if ($cover instanceof UploadedFile) {
                $this->covers->updateCover($actor, $group, $cover);
            }

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
