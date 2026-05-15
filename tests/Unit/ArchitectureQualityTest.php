<?php

use Illuminate\Support\Collection;

it('keeps application code free of debugging output helpers', function (): void {
    $violations = qualityPhpFiles([
        app_path(),
        base_path('routes'),
        database_path('factories'),
        database_path('seeders'),
    ])
        ->filter(fn (string $path): bool => preg_match('/\b(dd|dump|ray|var_dump|print_r)\s*\(/', qualitySource($path)) === 1)
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('keeps environment access in configuration files', function (): void {
    $violations = qualityPhpFiles([
        app_path(),
        base_path('routes'),
        database_path('factories'),
        database_path('seeders'),
    ])
        ->filter(fn (string $path): bool => preg_match('/\benv\s*\(/', qualitySource($path)) === 1)
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('does not add application console command classes', function (): void {
    $consolePath = app_path('Console');

    if (! is_dir($consolePath)) {
        expect(true)->toBeTrue();

        return;
    }

    expect(qualityPhpFiles([$consolePath])->all())->toBeEmpty();
});

it('keeps controllers requests and models in domain folders', function (): void {
    $rootControllerFiles = glob(app_path('Http/Controllers/*.php')) ?: [];
    $rootRequestFiles = glob(app_path('Http/Requests/*.php')) ?: [];
    $rootModelFiles = glob(app_path('Models/*.php')) ?: [];

    expect(array_values($rootControllerFiles))->toBe([app_path('Http/Controllers/Controller.php')]);
    expect($rootRequestFiles)->toBeEmpty();
    expect($rootModelFiles)->toBeEmpty();
});

it('uses conventional suffixes for controllers and form requests', function (): void {
    $controllerViolations = qualityPhpFiles([app_path('Http/Controllers')])
        ->reject(fn (string $path): bool => str_ends_with($path, '/Controller.php'))
        ->filter(fn (string $path): bool => ! str_ends_with($path, 'Controller.php'))
        ->values()
        ->all();

    $requestViolations = qualityPhpFiles([app_path('Http/Requests')])
        ->filter(fn (string $path): bool => ! str_ends_with($path, 'Request.php'))
        ->values()
        ->all();

    expect($controllerViolations)->toBeEmpty();
    expect($requestViolations)->toBeEmpty();
});

/**
 * @param  list<string>  $paths
 * @return Collection<int, string>
 */
function qualityPhpFiles(array $paths): Collection
{
    return collect($paths)
        ->filter(fn (string $path): bool => is_dir($path))
        ->flatMap(function (string $path): array {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            return collect(iterator_to_array($files))
                ->filter(fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php')
                ->map(fn (SplFileInfo $file): string => $file->getPathname())
                ->all();
        })
        ->sort()
        ->values();
}

function qualitySource(string $path): string
{
    return (string) file_get_contents($path);
}
