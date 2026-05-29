<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class PostMetadataService
{
    public function __construct(private readonly ?ClientInterface $httpClient = null) {}

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
            'link' => ['url', 'title', 'description', 'image', 'domain'],
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

        $url = $this->extractFirstUrl((string) $body);

        if ($url === null) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return [
            'url' => $url,
            'title' => is_string($host) && $host !== '' ? $host : $url,
        ];
    }

    public function extractFirstUrl(?string $value): ?string
    {
        $value = (string) $value;

        if (! preg_match('/https?:\/\/[^\s<>"\']+/i', $value, $matches)) {
            return null;
        }

        $url = mb_substr(rtrim($matches[0], '.,!?)]}'), 0, 500);

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * @return array{url: string, title: string, description?: string, image?: string, domain?: string}|null
     */
    public function fetchLinkPreview(string $url): ?array
    {
        $url = $this->extractFirstUrl($url);

        if ($url === null || ! $this->isAllowedPreviewUrl($url)) {
            return null;
        }

        try {
            $response = $this->client()->request('GET', $url, [
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'track_redirects' => true,
                ],
                'connect_timeout' => 5,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'PetSocial Link Preview Bot/1.0',
                ],
                'http_errors' => false,
                'timeout' => 5,
            ]);
        } catch (GuzzleException) {
            return null;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return null;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml')) {
            return null;
        }

        $html = mb_substr((string) $response->getBody(), 0, 1_000_000);
        $finalUrl = $this->finalResponseUrl($response, $url);

        return $this->parseOpenGraphHtml($html, $finalUrl);
    }

    public function isAllowedPreviewUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.lan')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.invalid')
            || str_ends_with($host, '.home.arpa')
        ) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (! is_array($records) || $records === []) {
            return true;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip) && ! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{url: string, title: string, description?: string, image?: string, domain?: string}|null
     */
    private function parseOpenGraphHtml(string $html, string $url): ?array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            $loaded = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $canonicalUrl = $this->absoluteUrl($this->linkHref($xpath, 'canonical'), $url) ?? $url;
        $title = $this->metaContent($xpath, 'property', 'og:title')
            ?? $this->metaContent($xpath, 'name', 'twitter:title')
            ?? $this->titleText($xpath)
            ?? parse_url($canonicalUrl, PHP_URL_HOST)
            ?? $canonicalUrl;
        $description = $this->metaContent($xpath, 'property', 'og:description')
            ?? $this->metaContent($xpath, 'name', 'description')
            ?? $this->metaContent($xpath, 'name', 'twitter:description');
        $image = $this->absoluteUrl(
            $this->metaContent($xpath, 'property', 'og:image') ?? $this->metaContent($xpath, 'name', 'twitter:image'),
            $canonicalUrl,
        );
        $host = parse_url($canonicalUrl, PHP_URL_HOST);

        return $this->normalizePreviewPayload([
            'url' => $canonicalUrl,
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'domain' => is_string($host) ? preg_replace('/^www\./i', '', $host) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{url: string, title: string, description?: string, image?: string, domain?: string}|null
     */
    private function normalizePreviewPayload(array $payload): ?array
    {
        $url = $this->normalizeScalar($payload['url'] ?? null);
        $title = $this->normalizeScalar($payload['title'] ?? null);

        if ($url === null || $title === null || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $preview = [
            'url' => $url,
            'title' => mb_substr(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 200),
        ];

        foreach (['description' => 500, 'image' => 500, 'domain' => 120] as $key => $limit) {
            $value = $this->normalizeScalar($payload[$key] ?? null);

            if ($value !== null) {
                $preview[$key] = mb_substr(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, $limit);
            }
        }

        return $preview;
    }

    private function client(): ClientInterface
    {
        return $this->httpClient ?? new Client;
    }

    private function finalResponseUrl(ResponseInterface $response, string $fallbackUrl): string
    {
        $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
        $lastRedirect = end($redirectHistory);

        return is_string($lastRedirect) && filter_var($lastRedirect, FILTER_VALIDATE_URL)
            ? $lastRedirect
            : $fallbackUrl;
    }

    private function metaContent(DOMXPath $xpath, string $attribute, string $value): ?string
    {
        $query = sprintf(
            '//meta[translate(@%s, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]/@content',
            $attribute,
            strtolower($value),
        );

        $nodes = $xpath->query($query);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node ? $this->normalizeScalar($node->nodeValue) : null;
    }

    private function linkHref(DOMXPath $xpath, string $rel): ?string
    {
        $nodes = $xpath->query(sprintf('//link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " %s ")]/@href', strtolower($rel)));
        $node = $nodes === false ? null : $nodes->item(0);

        return $node ? $this->normalizeScalar($node->nodeValue) : null;
    }

    private function titleText(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//title');
        $node = $nodes === false ? null : $nodes->item(0);

        return $node ? $this->normalizeScalar($node->textContent) : null;
    }

    private function absoluteUrl(?string $url, string $baseUrl): ?string
    {
        if ($url === null) {
            return null;
        }

        try {
            $resolved = (string) UriResolver::resolve(new Uri($baseUrl), new Uri($url));
        } catch (Throwable) {
            return null;
        }

        return filter_var($resolved, FILTER_VALIDATE_URL) ? $resolved : null;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
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
