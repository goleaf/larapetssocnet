<?php

it('defines a complete local quality toolchain', function (): void {
    $composer = qualityJson(project_path('composer.json'));

    expect($composer['require-dev'] ?? [])->toMatchArray([
        'driftingly/rector-laravel' => '^2.3',
        'larastan/larastan' => '^3.9',
        'laravel/pint' => '^1.29',
        'pestphp/pest' => '^4.7',
        'pestphp/pest-plugin-laravel' => '^4.1',
        'pestphp/pest-plugin-type-coverage' => '^4.0',
        'phpstan/phpstan' => '^2.1',
        'rector/rector' => '^2.4',
    ]);

    expect($composer['scripts'] ?? [])->toHaveKeys([
        'analyse',
        'build',
        'lint',
        'lint:fix',
        'quality',
        'rector',
        'rector:dry',
        'test',
        'test:feature',
        'test:type-coverage',
        'test:unit',
    ]);
});

it('loads larastan and keeps phpstan cache inside the project', function (): void {
    $phpstan = (string) file_get_contents(project_path('phpstan.neon'));

    expect($phpstan)->toContain('vendor/larastan/larastan/extension.neon');
    expect($phpstan)->toContain('phpstan-baseline.neon');
    expect($phpstan)->toContain('storage/framework/cache/phpstan');
});

it('keeps rector deterministic for local and ci runs', function (): void {
    $rector = (string) file_get_contents(project_path('rector.php'));

    expect($rector)->toContain("withCache(cacheDirectory: __DIR__.'/storage/framework/cache/rector')");
    expect($rector)->toContain('withParallel(timeoutSeconds: 120, maxNumberOfProcess: 8)');
    expect($rector)->toContain("withMemoryLimit('1G')");
    expect($rector)->toContain('LaravelSetProvider::class');
});

it('configures pint with the laravel preset', function (): void {
    $pint = qualityJson(project_path('pint.json'));

    expect($pint)->toMatchArray([
        'preset' => 'laravel',
        'exclude' => [
            'bootstrap/cache',
            'storage',
            'vendor',
        ],
    ]);
});

/**
 * @return array<string, mixed>
 */
function qualityJson(string $path): array
{
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function project_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);

    return $path === '' ? $root : $root.'/'.$path;
}
