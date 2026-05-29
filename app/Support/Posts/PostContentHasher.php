<?php

namespace App\Support\Posts;

use Illuminate\Support\Str;

class PostContentHasher
{
    public function normalized(?string $content): string
    {
        $text = strip_tags((string) $content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim(Str::lower($text));
    }

    public function hash(?string $content): ?string
    {
        $normalized = $this->normalized($content);

        return $normalized === '' ? null : hash('sha256', $normalized);
    }
}
