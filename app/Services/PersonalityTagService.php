<?php

namespace App\Services;

use App\Models\Pet;

class PersonalityTagService
{
    public const MAX = 10;

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
     * Sync personality tags for a pet (stored as JSON on pet model).
     *
     * @param  list<string>  $tags
     * @return list<string> The cleaned tags that were saved
     */
    public function sync(Pet $pet, array $tags): array
    {
        $cleaned = collect($tags)
            ->map(fn (string $t): string => strtolower(
                preg_replace('/[^a-z0-9 ]/i', '', trim($t))
            ))
            ->filter(fn (string $t): bool => strlen($t) >= 2 && strlen($t) <= 30)
            ->unique()
            ->take(self::MAX)
            ->values()
            ->all();

        $pet->update(['personality_tags' => $cleaned]);

        return $cleaned;
    }

    /**
     * @return list<string>
     */
    public function getSuggestions(): array
    {
        return self::SUGGESTIONS;
    }
}
