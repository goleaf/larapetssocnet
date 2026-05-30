<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CommentGifService
{
    /**
     * @return list<array{id: string, title: string, gif_url: string, gif_preview_url: string|null, gif_provider: string}>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        $endpoint = (string) config('services.gif.endpoint', '');
        $apiKey = (string) config('services.gif.key', '');
        $provider = (string) config('services.gif.provider', 'tenor');

        if ($query === '' || $endpoint === '' || $apiKey === '') {
            return [];
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.gif.timeout', 3))
                ->get($endpoint, $this->requestParameters($provider, $query, $apiKey));
        } catch (ConnectionException) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();

        return match ($provider) {
            'giphy' => $this->parseGiphyResults(is_array($payload) ? $payload : []),
            default => $this->parseTenorResults(is_array($payload) ? $payload : []),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function requestParameters(string $provider, string $query, string $apiKey): array
    {
        $limit = (int) config('services.gif.limit', 12);

        if ($provider === 'giphy') {
            return [
                'api_key' => $apiKey,
                'q' => $query,
                'limit' => $limit,
                'rating' => 'pg-13',
            ];
        }

        return [
            'key' => $apiKey,
            'q' => $query,
            'limit' => $limit,
            'media_filter' => 'minimal',
            'contentfilter' => 'medium',
            'client_key' => (string) config('services.gif.client_key', 'petsocial'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{id: string, title: string, gif_url: string, gif_preview_url: string|null, gif_provider: string}>
     */
    private function parseTenorResults(array $payload): array
    {
        $results = data_get($payload, 'results', []);

        if (! is_array($results)) {
            return [];
        }

        return collect($results)
            ->map(function (mixed $result): ?array {
                if (! is_array($result)) {
                    return null;
                }

                $gifUrl = data_get($result, 'media_formats.gif.url')
                    ?? data_get($result, 'media_formats.mediumgif.url')
                    ?? data_get($result, 'media_formats.tinygif.url');

                if (! is_string($gifUrl) || $gifUrl === '') {
                    return null;
                }

                $previewUrl = data_get($result, 'media_formats.tinygif.url');

                return [
                    'id' => (string) data_get($result, 'id', md5($gifUrl)),
                    'title' => (string) data_get($result, 'content_description', 'GIF'),
                    'gif_url' => $gifUrl,
                    'gif_preview_url' => is_string($previewUrl) ? $previewUrl : null,
                    'gif_provider' => 'tenor',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{id: string, title: string, gif_url: string, gif_preview_url: string|null, gif_provider: string}>
     */
    private function parseGiphyResults(array $payload): array
    {
        $results = data_get($payload, 'data', []);

        if (! is_array($results)) {
            return [];
        }

        return collect($results)
            ->map(function (mixed $result): ?array {
                if (! is_array($result)) {
                    return null;
                }

                $gifUrl = data_get($result, 'images.original.url')
                    ?? data_get($result, 'images.fixed_height.url');

                if (! is_string($gifUrl) || $gifUrl === '') {
                    return null;
                }

                $previewUrl = data_get($result, 'images.fixed_width_small.url');

                return [
                    'id' => (string) data_get($result, 'id', md5($gifUrl)),
                    'title' => (string) data_get($result, 'title', 'GIF'),
                    'gif_url' => $gifUrl,
                    'gif_preview_url' => is_string($previewUrl) ? $previewUrl : null,
                    'gif_provider' => 'giphy',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
