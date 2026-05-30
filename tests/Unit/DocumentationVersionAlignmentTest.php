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
                '/docs\/(?:10|11|12)\.x|Laravel\s+(?:8|9|10|11|12)(?:\.x)?|laravel\/framework \(LARAVEL\) - v(?:8|9|10|11|12)|PHP\s+8\.(?:1|2)\+?|Pest\s+3|pestphp\/pest \(PEST\) - v3|PHPUnit\s+11|phpunit\/phpunit \(PHPUNIT\) - v11|Tailwind CSS v3|tailwindcss \(TAILWINDCSS\) - v3|app\/Console\/Kernel\.php|protected\s+\$casts/',
                $contents,
                $matches
            );

            $found = array_values(array_unique($matches[0]));

            return $found === [] ? [] : [versionAlignmentRelativePath($path) => $found];
        })
        ->all();

    expect($staleGuidance)->toBeEmpty();
});

it('keeps installed Laravel Superpowers skills pinned to the current project baseline', function (): void {
    $skillFiles = laravelSuperpowersSkillFiles();

    expect($skillFiles)->toHaveCount(52);

    $requiredNeedles = [
        '## Laravel 13 Baseline',
        'Laravel 13.12.0',
        'PHP 8.4+',
        'Pest 4',
        'PHPUnit 12',
        'Tailwind CSS 4',
        'Livewire 4.3',
    ];

    $skillsWithoutBaseline = $skillFiles
        ->mapWithKeys(function (string $path) use ($requiredNeedles): array {
            $contents = file_get_contents($path);

            if (! is_string($contents)) {
                return [versionAlignmentRelativePath($path) => ['unreadable']];
            }

            $missing = collect($requiredNeedles)
                ->reject(fn (string $needle): bool => str_contains($contents, $needle))
                ->values()
                ->all();

            return $missing === [] ? [] : [versionAlignmentRelativePath($path) => $missing];
        })
        ->all();

    expect($skillsWithoutBaseline)->toBeEmpty();
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

/**
 * @return Collection<int, string>
 */
function laravelSuperpowersSkillFiles(): Collection
{
    return collect(glob(dirname(__DIR__, 2).'/.claude/skills/*/SKILL.md') ?: [])
        ->filter(function (string $path): bool {
            $contents = file_get_contents($path);

            return is_string($contents) && str_contains($contents, 'name: laravel:');
        })
        ->sort()
        ->values();
}

function versionAlignmentRelativePath(string $path, ?string $root = null): string
{
    $root ??= dirname(__DIR__, 2);

    return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}
