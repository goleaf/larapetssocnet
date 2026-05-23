<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class LocationAutocompleteService
{
    /**
     * @return list<array{label: string, latitude: float, longitude: float}>
     */
    public function suggest(string $query, int $limit = 5): array
    {
        $query = Str::squish($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $endpoint = trim((string) config('services.geocoding.endpoint', ''));

        if ($endpoint === '') {
            return [];
        }

        try {
            $response = Http::acceptJson()
                ->timeout((float) config('services.geocoding.timeout', 2))
                ->get($endpoint, array_filter([
                    'q' => $query,
                    'query' => $query,
                    'limit' => $limit,
                    'key' => config('services.geocoding.key'),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            if (! $response->ok()) {
                return [];
            }

            return $this->normalizeResponse($response->json(), $limit);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{label: string, latitude: float, longitude: float}>
     */
    private function normalizeResponse(mixed $payload, int $limit): array
    {
        $items = $this->extractItems($payload);
        $suggestions = [];

        foreach ($items as $item) {
            $suggestion = $this->normalizeItem($item);

            if ($suggestion === null) {
                continue;
            }

            $dedupeKey = Str::lower($suggestion['label']).'|'.$suggestion['latitude'].'|'.$suggestion['longitude'];
            $suggestions[$dedupeKey] = $suggestion;

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return array_values($suggestions);
    }

    /**
     * @return list<mixed>
     */
    private function extractItems(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        foreach (['features', 'results', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        return [$payload];
    }

    /**
     * @return array{label: string, latitude: float, longitude: float}|null
     */
    private function normalizeItem(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $properties = is_array($item['properties'] ?? null) ? $item['properties'] : [];
        $geometry = is_array($item['geometry'] ?? null) ? $item['geometry'] : [];
        $coordinates = is_array($geometry['coordinates'] ?? null) ? $geometry['coordinates'] : [];

        $label = (string) ($item['label']
            ?? $item['display_name']
            ?? $item['formatted']
            ?? $item['name']
            ?? $properties['label']
            ?? $properties['display_name']
            ?? $properties['formatted']
            ?? $properties['name']
            ?? $properties['place_name']
            ?? '');

        $latitude = $item['latitude']
            ?? $item['lat']
            ?? $properties['latitude']
            ?? $properties['lat']
            ?? $coordinates[1]
            ?? null;

        $longitude = $item['longitude']
            ?? $item['lng']
            ?? $item['lon']
            ?? $properties['longitude']
            ?? $properties['lng']
            ?? $properties['lon']
            ?? $coordinates[0]
            ?? null;

        if (trim($label) === '' || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return [
            'label' => Str::squish($label),
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }
}
