<?php

namespace App\Services;

use App\Models\Pets\Pet;
use App\Models\Pets\PetTag;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PersonalityTagService
{
    public const MAX = 10;

    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 30;

    /** @var list<string> */
    public const SUGGESTIONS = [
        'playful',
        'energetic',
        'calm',
        'shy',
        'friendly',
        'independent',
        'cuddly',
        'protective',
        'gentle',
        'silly',
        'stubborn',
        'smart',
        'loyal',
        'vocal',
        'lazy',
        'adventurous',
    ];

    /**
     * Normalize raw personality tags into a canonical list.
     *
     * @return list<string>
     */
    public function normalize(mixed $rawTags): array
    {
        $tags = is_string($rawTags) ? explode(',', $rawTags) : Arr::wrap($rawTags);

        $min = $this->minLength();
        $max = $this->maxLength();
        $limit = $this->maxTags();

        return collect($tags)
            ->map(function (mixed $tag): string {
                $cleaned = preg_replace('/[^a-z0-9 ]/i', '', trim((string) $tag));
                $cleaned = preg_replace('/\s+/', ' ', (string) $cleaned);

                return strtolower((string) $cleaned);
            })
            ->filter(fn (string $tag): bool => $tag !== '' && strlen($tag) >= $min && strlen($tag) <= $max)
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Sync personality tags for a pet (JSON + pet_tags records).
     *
     * @param  list<string>  $tags
     * @return list<string> The cleaned tags that were saved
     */
    public function sync(Pet $pet, array $tags): array
    {
        $cleaned = $this->normalize($tags);

        $pet->update(['personality_tags' => $cleaned]);
        $this->syncTagRecords($pet, $cleaned);

        return $cleaned;
    }

    /**
     * @param  list<string>  $tags
     */
    public function syncTagRecords(Pet $pet, array $tags): void
    {
        $payload = collect($tags)
            ->map(static fn (string $tag): array => [
                'slug' => Str::slug($tag),
                'name' => $tag,
            ])
            ->filter(static fn (array $tag): bool => $tag['slug'] !== '')
            ->values();

        if ($payload->isEmpty()) {
            PetTag::query()->where('pet_id', $pet->getKey())->delete();

            return;
        }

        PetTag::query()
            ->where('pet_id', $pet->getKey())
            ->whereNotIn('slug', $payload->pluck('slug'))
            ->delete();

        foreach ($payload as $tag) {
            PetTag::query()->updateOrCreate([
                'pet_id' => $pet->getKey(),
                'slug' => $tag['slug'],
            ], [
                'name' => $tag['name'],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function getSuggestions(): array
    {
        return (array) config('pets.personality_tags.suggestions', self::SUGGESTIONS);
    }

    public function maxTags(): int
    {
        return (int) config('pets.personality_tags.max', self::MAX);
    }

    public function minLength(): int
    {
        return (int) config('pets.personality_tags.min_length', self::MIN_LENGTH);
    }

    public function maxLength(): int
    {
        return (int) config('pets.personality_tags.max_length', self::MAX_LENGTH);
    }
}
