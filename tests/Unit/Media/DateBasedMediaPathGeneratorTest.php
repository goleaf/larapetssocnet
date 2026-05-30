<?php

use App\Support\Media\DateBasedMediaPathGenerator;
use Carbon\CarbonImmutable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('builds date-partitioned paths for post media and derivatives', function (): void {
    $media = new Media;
    $media->id = 42;
    $media->created_at = CarbonImmutable::parse('2026-05-30 12:00:00');

    $generator = new DateBasedMediaPathGenerator;

    expect($generator->getPath($media))->toBe('posts/2026/05/30/42/')
        ->and($generator->getPathForConversions($media))->toBe('posts/2026/05/30/42/conversions/')
        ->and($generator->getPathForResponsiveImages($media))->toBe('posts/2026/05/30/42/responsive-images/');
});
