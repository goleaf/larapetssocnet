<?php

namespace App\Services;

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetMilestone;
use App\Support\Posts\PostCreationInput;
use Illuminate\Support\Facades\DB;

class PetMilestoneService
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly CreatePostAction $createPostAction,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Pet $pet, User $actor, array $attributes): PetMilestone
    {
        return DB::transaction(function () use ($pet, $actor, $attributes): PetMilestone {
            $body = $this->nullableString($attributes['body'] ?? null);
            $shareAsPost = (bool) ($attributes['share_as_post'] ?? false);

            $milestone = PetMilestone::query()->create([
                'pet_id' => $pet->getKey(),
                'user_id' => $actor->getKey(),
                'milestone_type' => (string) ($attributes['milestone_type'] ?? PetMilestone::TYPE_LIFE_EVENT),
                'title' => (string) $attributes['title'],
                'body' => $body,
                'body_html' => $body ? $this->contentService->process($body) : null,
                'occurred_on' => $attributes['occurred_on'],
                'share_as_post' => $shareAsPost,
            ]);

            if ($shareAsPost) {
                $post = $this->createMilestonePost($pet, $actor, $milestone);
                $milestone->forceFill(['post_id' => $post->getKey()])->save();
            }

            return $milestone->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PetMilestone $milestone, array $attributes): PetMilestone
    {
        $body = array_key_exists('body', $attributes)
            ? $this->nullableString($attributes['body'])
            : $milestone->body;

        $milestone->update([
            'milestone_type' => $attributes['milestone_type'] ?? $milestone->milestone_type,
            'title' => $attributes['title'] ?? $milestone->title,
            'body' => $body,
            'body_html' => $body ? $this->contentService->process($body) : null,
            'occurred_on' => $attributes['occurred_on'] ?? $milestone->occurred_on,
        ]);

        return $milestone->refresh();
    }

    private function createMilestonePost(Pet $pet, User $actor, PetMilestone $milestone): Post
    {
        $body = collect([
            'Milestone: '.$milestone->title,
            $milestone->body,
        ])->filter()->join("\n\n");

        return $this->createPostAction->handle($actor, PostCreationInput::fromUserInput($actor, [
            'body' => $body,
            'pet_id' => $pet->getKey(),
            'tagged_pets' => [$pet->getKey()],
            'status' => PostStatus::Published,
            'visibility' => $pet->is_public ? Post::VISIBILITY_PUBLIC : Post::VISIBILITY_PRIVATE,
            'is_system_generated' => true,
            'confirmed_duplicate' => true,
            'system_source' => 'pet_milestone',
            'metadata' => [
                'source' => 'pet_milestone',
                'milestone_type' => $milestone->milestone_type,
                'milestone_id' => $milestone->getKey(),
            ],
        ]))->createdPost();
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
