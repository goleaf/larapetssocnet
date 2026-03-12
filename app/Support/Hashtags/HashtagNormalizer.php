<?php

namespace App\Support\Hashtags;

use Illuminate\Support\Str;

class HashtagNormalizer
{
    public function normalize(string $tag): ?string
    {
        $trimmed = trim($tag);

        if ($trimmed === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9_]/', '', Str::lower($trimmed));

        if ($normalized === null || $normalized === '') {
            return null;
        }

        $length = mb_strlen($normalized);
        $minLength = (int) config('hashtags.min_length', 1);
        $maxLength = (int) config('hashtags.max_length', 50);

        if ($length < $minLength || $length > $maxLength) {
            return null;
        }

        return $normalized;
    }

    public function normalizeFromInput(string $input): ?string
    {
        return $this->normalize(ltrim(trim($input), '#'));
    }

    public function normalizeFromSlug(string $slug): ?string
    {
        return $this->normalize(ltrim(trim($slug), '#'));
    }
}
