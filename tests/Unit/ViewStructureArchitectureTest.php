<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

it('keeps page views grouped by domain folders', function (): void {
    $rootDirectories = collect(glob(resource_path('views/*'), GLOB_ONLYDIR) ?: [])
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values()
        ->all();

    expect($rootDirectories)->toBe([
        'activities',
        'admin',
        'auth',
        'components',
        'dashboard',
        'dev',
        'discovery',
        'emails',
        'errors',
        'feed',
        'gamification',
        'groups',
        'layouts',
        'marketplace',
        'media',
        'messaging',
        'onboarding',
        'pages',
        'pets',
        'posts',
        'profile',
        'settings',
        'social',
    ]);
});

it('keeps root view files to framework entry templates only', function (): void {
    $rootBladeFiles = collect(glob(resource_path('views/*.blade.php')) ?: [])
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values()
        ->all();

    expect($rootBladeFiles)->toBe(['welcome.blade.php']);
});

it('resolves every literal view and blade include reference', function (): void {
    $references = viewStructurePhpFiles([
        app_path('Http/Controllers'),
        base_path('routes'),
        resource_path('views'),
    ])
        ->flatMap(fn (string $path): array => viewStructureReferences($path))
        ->unique()
        ->sort()
        ->values();

    $missing = $references
        ->reject(fn (string $view): bool => View::exists($view))
        ->values()
        ->all();

    expect($missing)->toBeEmpty('Missing view references: '.implode(', ', $missing));
});

/**
 * @param  list<string>  $paths
 * @return Collection<int, string>
 */
function viewStructurePhpFiles(array $paths): Collection
{
    return collect($paths)
        ->filter(fn (string $path): bool => is_dir($path))
        ->flatMap(function (string $path): array {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            return collect(iterator_to_array($files))
                ->filter(fn (SplFileInfo $file): bool => $file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'], true))
                ->map(fn (SplFileInfo $file): string => $file->getPathname())
                ->all();
        })
        ->sort()
        ->values();
}

/**
 * @return list<string>
 */
function viewStructureReferences(string $path): array
{
    $source = (string) file_get_contents($path);

    $patterns = [
        '/\bview\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/View::make\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/@(?:extends|include|includeIf|includeIsolated|component)\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/@include(?:When|Unless)\s*\([^,]+,\s*[\'"]([^\'"]+)[\'"]/',
        '/@includeFirst\s*\(\s*\[\s*[\'"]([^\'"]+)[\'"]/',
    ];

    return collect($patterns)
        ->flatMap(function (string $pattern) use ($source): array {
            preg_match_all($pattern, $source, $matches);

            return $matches[1] ?? [];
        })
        ->reject(fn (string $view): bool => str_contains($view, '$') || str_contains($view, '{'))
        ->unique()
        ->values()
        ->all();
}
