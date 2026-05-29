<?php

use App\Services\PostMetadataService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

test('metadata normalization keeps allowed fields', function (): void {
    $service = app(PostMetadataService::class);

    $input = [
        'link' => [
            'url' => 'https://example.com',
            'title' => 'Example',
            'description' => 'A link preview',
            'image' => 'https://example.com/image.png',
            'extra' => 'ignored',
        ],
        'mood' => 'happy',
        'activity' => 'walking',
        'source' => 'composer',
        'context' => 'profile',
        'extra' => 'ignored',
    ];

    $normalized = $service->normalize($input);

    expect($normalized)->toHaveKey('link')
        ->and($normalized['link'])->toHaveKey('url', 'https://example.com')
        ->and($normalized)->toHaveKey('mood', 'happy')
        ->and($normalized)->toHaveKey('activity', 'walking')
        ->and($normalized)->toHaveKey('source', 'composer')
        ->and($normalized)->toHaveKey('context', 'profile')
        ->and($normalized)->not->toHaveKey('extra');
});

test('metadata normalization drops empty values', function (): void {
    $service = app(PostMetadataService::class);

    $normalized = $service->normalize([
        'mood' => ' ',
        'link' => ['url' => ''],
    ]);

    expect($normalized)->toBeNull();
});

test('open graph metadata is fetched and normalized from html', function (): void {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], <<<'HTML'
            <html>
                <head>
                    <meta property="og:title" content="Luna at the park">
                    <meta property="og:description" content="A sunny afternoon walk">
                    <meta property="og:image" content="/images/luna.jpg">
                    <link rel="canonical" href="/stories/luna">
                </head>
            </html>
        HTML),
    ]);

    $service = new PostMetadataService(new Client([
        'handler' => HandlerStack::create($mock),
    ]));

    $preview = $service->fetchLinkPreview('https://example.com/original');

    expect($preview)->toMatchArray([
        'url' => 'https://example.com/stories/luna',
        'title' => 'Luna at the park',
        'description' => 'A sunny afternoon walk',
        'image' => 'https://example.com/images/luna.jpg',
        'domain' => 'example.com',
    ]);
});

test('link preview fetching rejects local network urls', function (): void {
    $service = app(PostMetadataService::class);

    expect($service->isAllowedPreviewUrl('http://127.0.0.1/admin'))->toBeFalse()
        ->and($service->isAllowedPreviewUrl('http://localhost/admin'))->toBeFalse()
        ->and($service->isAllowedPreviewUrl('ftp://example.com/file'))->toBeFalse();
});
