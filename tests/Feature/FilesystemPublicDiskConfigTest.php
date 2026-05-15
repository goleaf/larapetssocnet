<?php

declare(strict_types=1);

afterEach(function (): void {
    putenv('PUBLIC_DISK_ROOT');
    putenv('PUBLIC_DISK_URL');

    unset($_ENV['PUBLIC_DISK_ROOT'], $_SERVER['PUBLIC_DISK_ROOT']);
    unset($_ENV['PUBLIC_DISK_URL'], $_SERVER['PUBLIC_DISK_URL']);
});

it('allows the public disk path and url to be configured for shared hosting', function (): void {
    $root = base_path('public/storage');
    $url = 'https://petsocial.prus.dev/storage';

    putenv("PUBLIC_DISK_ROOT={$root}");
    putenv("PUBLIC_DISK_URL={$url}");

    $_ENV['PUBLIC_DISK_ROOT'] = $root;
    $_ENV['PUBLIC_DISK_URL'] = $url;

    $filesystem = require config_path('filesystems.php');

    expect($filesystem['disks']['public']['root'])->toBe($root)
        ->and($filesystem['disks']['public']['url'])->toBe($url);
});
