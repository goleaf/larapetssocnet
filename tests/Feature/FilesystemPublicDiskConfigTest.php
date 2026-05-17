<?php

declare(strict_types=1);

use App\Models\Groups\Group;
use App\Models\Marketplace\ListingImage;

afterEach(function (): void {
    putenv('PUBLIC_DISK_ROOT');
    putenv('PUBLIC_DISK_URL');

    unset($_ENV['PUBLIC_DISK_ROOT'], $_SERVER['PUBLIC_DISK_ROOT']);
    unset($_ENV['PUBLIC_DISK_URL'], $_SERVER['PUBLIC_DISK_URL']);
});

it('allows the public disk path and url to be configured for shared hosting', function (): void {
    $root = storage_path('app/public');
    $url = 'https://petsocial.prus.dev/storage';

    putenv("PUBLIC_DISK_ROOT={$root}");
    putenv("PUBLIC_DISK_URL={$url}");

    $_ENV['PUBLIC_DISK_ROOT'] = $root;
    $_ENV['PUBLIC_DISK_URL'] = $url;

    $filesystem = require config_path('filesystems.php');

    expect($filesystem['disks']['public']['root'])->toBe($root)
        ->and($filesystem['disks']['public']['url'])->toBe($url);
});

it('uses the configured public disk url for shared hosting media paths', function (): void {
    config()->set('filesystems.disks.public.url', 'https://petsocial.prus.dev/storage');

    $group = new Group([
        'avatar' => 'groups/avatar.jpg',
        'cover_image' => 'groups/cover.jpg',
        'avatar_path' => null,
        'cover_image_path' => null,
    ]);
    $listingImage = new ListingImage([
        'file_path' => 'listings/photo.jpg',
    ]);

    expect($group->avatarUrl())->toBe('https://petsocial.prus.dev/storage/groups/avatar.jpg')
        ->and($group->coverUrl())->toBe('https://petsocial.prus.dev/storage/groups/cover.jpg')
        ->and($listingImage->url)->toBe('https://petsocial.prus.dev/storage/listings/photo.jpg');
});
