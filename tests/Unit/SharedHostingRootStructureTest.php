<?php

it('serves the Laravel front controller from the project root', function (): void {
    $root = sharedHostingRootPath();

    expect(is_file($root.'/index.php'))->toBeTrue()
        ->and(is_file($root.'/.htaccess'))->toBeTrue()
        ->and(is_dir($root.'/public'))->toBeFalse()
        ->and(file_get_contents($root.'/index.php'))->toContain("__DIR__.'/bootstrap/app.php'")
        ->and(public_path())->toBe($root);
});

it('keeps root hosted Laravel internals behind Apache deny rules', function (): void {
    $htaccess = (string) file_get_contents(sharedHostingRootPath('.htaccess'));

    foreach (['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'storage/(?:app/(?!public', 'tests', 'vendor'] as $protectedPath) {
        expect($htaccess)->toContain($protectedPath);
    }

    expect($htaccess)
        ->toContain('storage/app/public/$1')
        ->toContain('composer\\.(?:json|lock)')
        ->toContain('RewriteRule (^|/)\\. - [F,L]');
});

it('builds frontend assets into the shared hosting root', function (): void {
    $vite = (string) file_get_contents(sharedHostingRootPath('vite.config.js'));
    $gitignore = (string) file_get_contents(sharedHostingRootPath('.gitignore'));

    expect($vite)
        ->toContain("outDir: 'build'")
        ->toContain("hotFile: 'hot'")
        ->not->toContain("outDir: 'public/build'")
        ->and($gitignore)
        ->toContain('/build')
        ->toContain('/hot')
        ->not->toContain('/public/build');
});

function sharedHostingRootPath(string $path = ''): string
{
    $root = dirname(__DIR__, 2);

    return $path === '' ? $root : $root.'/'.$path;
}
