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

    expect($composer['scripts']['post-update-cmd'] ?? [])->toContain('@php artisan boost:update --ansi --ignore-skills');
});

it('configures laravel boost for project ai agents without syncing curated skills automatically', function (): void {
    $boost = qualityJson(project_path('boost.json'));

    expect($boost)->toMatchArray([
        'agents' => [
            'claude_code',
            'codex',
        ],
        'guidelines' => true,
        'mcp' => true,
    ]);

    expect($boost['skills'] ?? [])->toBe([
        'pest-testing',
        'tailwindcss-development',
    ]);
});

it('does not carry horizon configuration when horizon is not installed', function (): void {
    $composer = qualityJson(project_path('composer.json'));
    $requires = is_array($composer['require'] ?? null) ? $composer['require'] : [];
    $requiresDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];
    $lockedPackages = toolingComposerPackageNames();
    $requiresHorizon = array_key_exists('laravel/horizon', $requires)
        || array_key_exists('laravel/horizon', $requiresDev)
        || in_array('laravel/horizon', $lockedPackages, true);

    if (! $requiresHorizon) {
        expect(project_path('config/horizon.php'))->not->toBeFile();
        expect(toolingSource(project_path('skills/laravel.md')))->toContain('Do not add or publish `config/horizon.php` unless `laravel/horizon` is installed');
        expect(toolingSource(project_path('.claude/skills/queues-and-horizon/SKILL.md')))->toContain('Horizon is not installed in this project by default');

        return;
    }

    $horizonConfig = toolingSource(project_path('config/horizon.php'));

    expect($horizonConfig)->toContain('QueueName::workerOrder()');
    expect($horizonConfig)->toContain("'connection' => 'redis'");
    expect($horizonConfig)->toContain("'balance'");
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

function toolingSource(string $path): string
{
    return (string) file_get_contents($path);
}

/**
 * @return list<string>
 */
function toolingComposerPackageNames(): array
{
    $lockPath = project_path('composer.lock');

    if (! is_file($lockPath)) {
        return [];
    }

    $lock = qualityJson($lockPath);
    $packages = is_array($lock['packages'] ?? null) ? $lock['packages'] : [];
    $packagesDev = is_array($lock['packages-dev'] ?? null) ? $lock['packages-dev'] : [];

    return collect(array_merge($packages, $packagesDev))
        ->pluck('name')
        ->filter(fn (mixed $name): bool => is_string($name))
        ->values()
        ->all();
}

function project_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);

    return $path === '' ? $root : $root.'/'.$path;
}
