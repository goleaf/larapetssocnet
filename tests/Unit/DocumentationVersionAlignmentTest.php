<?php

use Illuminate\Support\Collection;

it('keeps markdown guidance aligned with the current toolchain versions', function (): void {
    $staleGuidance = markdownFilesForVersionAlignment()
        ->mapWithKeys(function (string $path): array {
            $contents = file_get_contents($path);

            if (! is_string($contents)) {
                return [];
            }

            preg_match_all(
                '/Laravel\s+(?:11|12)(?:\.x)?|laravel\/framework \(LARAVEL\) - v12|Pest\s+3|pestphp\/pest \(PEST\) - v3|PHPUnit\s+11|phpunit\/phpunit \(PHPUNIT\) - v11|Tailwind CSS v3|tailwindcss \(TAILWINDCSS\) - v3/',
                $contents,
                $matches
            );

            $found = array_values(array_unique($matches[0]));

            return $found === [] ? [] : [versionAlignmentRelativePath($path) => $found];
        })
        ->all();

    expect($staleGuidance)->toBeEmpty();
});

/**
 * @return Collection<int, string>
 */
function markdownFilesForVersionAlignment(): Collection
{
    $root = dirname(__DIR__, 2);
    $ignoredDirectories = [
        '.git',
        '.deploy',
        '.ai',
        'build',
        'node_modules',
        'storage',
        'vendor',
    ];

    $files = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $file) use ($ignoredDirectories, $root): bool {
                if (! $file->isDir()) {
                    return true;
                }

                $relativePath = versionAlignmentRelativePath($file->getPathname(), $root);

                return ! in_array($relativePath, $ignoredDirectories, true);
            }
        )
    );

    return collect(iterator_to_array($files))
        ->filter(fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'md')
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->sort()
        ->values();
}

function versionAlignmentRelativePath(string $path, ?string $root = null): string
{
    $root ??= dirname(__DIR__, 2);

    return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}
