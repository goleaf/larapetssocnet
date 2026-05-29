<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;

class PostMetadataService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public function normalize(?array $metadata): ?array
    {
        if (! $metadata) {
            return null;
        }

        $allowed = [
            'link' => ['url', 'title', 'description', 'image'],
            'mood' => null,
            'activity' => null,
            'source' => null,
            'context' => null,
        ];

        $normalized = [];

        foreach ($allowed as $key => $shape) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];

            if ($shape === null) {
                $scalar = $this->normalizeScalar($value);

                if ($scalar !== null) {
                    $normalized[$key] = $scalar;
                }

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $payload = [];

            foreach ($shape as $field) {
                $scalar = $this->normalizeScalar(Arr::get($value, $field));

                if ($scalar !== null) {
                    $payload[$field] = $scalar;
                }
            }

            if ($payload !== []) {
                $normalized[$key] = $payload;
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, string>|null
     */
    public function linkPreview(?string $body, ?array $metadata = null): ?array
    {
        $metadata = $this->normalize($metadata);
        $link = $metadata['link'] ?? null;

        if (is_array($link) && isset($link['url'])) {
            return $link;
        }

        $body = (string) $body;

        if (! preg_match('/https?:\/\/[^\s<]+/i', $body, $matches)) {
            return null;
        }

        $url = mb_substr(rtrim($matches[0], '.,!?)]}'), 0, 500);
        $host = parse_url($url, PHP_URL_HOST);

        return [
            'url' => $url,
            'title' => is_string($host) && $host !== '' ? $host : $url,
        ];
    }

    private function normalizeScalar(mixed $value): ?string
    {
        if (is_bool($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 500);
    }
}
