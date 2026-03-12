<?php

use App\Services\PostMetadataService;

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
