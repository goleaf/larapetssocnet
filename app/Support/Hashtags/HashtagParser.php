<?php

namespace App\Support\Hashtags;

class HashtagParser
{
    public function __construct(private readonly HashtagNormalizer $normalizer) {}

    public function pattern(): string
    {
        $maxLength = (int) config('hashtags.max_length', 50);

        return '/#([a-zA-Z0-9_]{1,'.$maxLength.'})/u';
    }

    /**
     * @return list<string>
     */
    public function extract(string $text): array
    {
        $limit = (int) config('hashtags.max_per_post', 20);

        return $this->extractInternal($text, $limit);
    }

    /**
     * @return list<string>
     */
    public function extractAll(string $text): array
    {
        return $this->extractInternal($text, null);
    }

    /**
     * @return list<string>
     */
    private function extractInternal(string $text, ?int $limit): array
    {
        preg_match_all($this->pattern(), $text, $matches);

        $unique = [];

        foreach ($matches[1] ?? [] as $rawTag) {
            $normalized = $this->normalizer->normalize($rawTag);

            if (! $normalized) {
                continue;
            }

            if (array_key_exists($normalized, $unique)) {
                continue;
            }

            $unique[$normalized] = true;

            if ($limit !== null && count($unique) >= $limit) {
                break;
            }
        }

        return array_keys($unique);
    }
}
