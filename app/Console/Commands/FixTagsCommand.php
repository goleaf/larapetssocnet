<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'views:fix-tags', description: 'Fix malformed Blade attribute spacing and optionally remove dark: utility classes.')]
class FixTagsCommand extends Command
{
    protected $signature = 'views:fix-tags
        {--path=* : One or more files or directories to process}
        {--remove-dark : Remove Tailwind dark: utility classes}
        {--dry-run : Show changed files without writing}';

    protected $description = 'Fix malformed Blade attribute spacing and optionally remove dark: utility classes.';

    public function handle(): int
    {
        $paths = $this->option('path');
        $removeDark = (bool) $this->option('remove-dark');
        $dryRun = (bool) $this->option('dry-run');
        $resolvedPaths = $this->resolvePaths($paths);
        $files = $this->collectFiles($resolvedPaths, $removeDark);

        if ($files === []) {
            $this->warn('No matching files found.');

            return self::SUCCESS;
        }

        $changedFiles = [];

        foreach ($files as $filePath) {
            $content = File::get($filePath);
            $updated = $content;

            if (Str::endsWith($filePath, '.blade.php')) {
                $updated = $this->normalizeTagSpacing($updated);
            }

            if ($removeDark) {
                $updated = $this->removeDarkUtilities($updated);
            }

            if ($content === $updated) {
                continue;
            }

            $changedFiles[] = $filePath;

            if (! $dryRun) {
                File::put($filePath, $updated);
            }
        }

        if ($changedFiles === []) {
            $this->info('No file changes were necessary.');

            return self::SUCCESS;
        }

        foreach ($changedFiles as $changedFile) {
            $this->line(($dryRun ? '[dry-run] ' : '').$this->relativePath($changedFile));
        }

        $summary = $dryRun
            ? 'Dry run complete'
            : 'File updates complete';

        $this->info("{$summary}: ".count($changedFiles).' file(s) changed.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function resolvePaths(array $paths): array
    {
        if ($paths === []) {
            return [
                resource_path('views'),
                resource_path('js'),
            ];
        }

        return array_map(static function (string $path): string {
            if (Str::startsWith($path, ['/'])) {
                return $path;
            }

            return base_path($path);
        }, $paths);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function collectFiles(array $paths, bool $includeFrontendFiles): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            if (File::isFile($path)) {
                if ($this->isSupportedFile($path, $includeFrontendFiles)) {
                    $files[] = $path;
                }

                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $pathname = $file->getPathname();

                if (! $this->isSupportedFile($pathname, $includeFrontendFiles)) {
                    continue;
                }

                $files[] = $pathname;
            }
        }

        return $files;
    }

    private function isSupportedFile(string $path, bool $includeFrontendFiles): bool
    {
        if (Str::endsWith($path, '.blade.php')) {
            return true;
        }

        if (! $includeFrontendFiles) {
            return false;
        }

        return Str::endsWith($path, ['.js', '.vue']);
    }

    private function normalizeTagSpacing(string $content): string
    {
        $length = strlen($content);
        $cursor = 0;
        $result = '';

        while ($cursor < $length) {
            $currentChar = $content[$cursor];

            if ($currentChar !== '<' || ! $this->looksLikeTagStart($content, $cursor, $length)) {
                $result .= $currentChar;
                $cursor++;

                continue;
            }

            $tag = $this->extractTag($content, $cursor, $length);

            if ($tag === null) {
                $result .= $currentChar;
                $cursor++;

                continue;
            }

            $result .= $this->fixTagSpacing($tag['value']);
            $cursor = $tag['next_index'];
        }

        return $result;
    }

    private function looksLikeTagStart(string $content, int $cursor, int $length): bool
    {
        if ($cursor + 1 >= $length) {
            return false;
        }

        return preg_match('/[a-zA-Z!\/?]/', $content[$cursor + 1]) === 1;
    }

    /**
     * @return array{value: string, next_index: int}|null
     */
    private function extractTag(string $content, int $start, int $length): ?array
    {
        $inDoubleQuotes = false;
        $inSingleQuotes = false;

        for ($cursor = $start + 1; $cursor < $length; $cursor++) {
            $char = $content[$cursor];

            if ($char === '"' && ! $inSingleQuotes) {
                $inDoubleQuotes = ! $inDoubleQuotes;

                continue;
            }

            if ($char === "'" && ! $inDoubleQuotes) {
                $inSingleQuotes = ! $inSingleQuotes;

                continue;
            }

            if ($char === '>' && ! $inDoubleQuotes && ! $inSingleQuotes) {
                return [
                    'value' => substr($content, $start, $cursor - $start + 1),
                    'next_index' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    private function fixTagSpacing(string $tag): string
    {
        $inDoubleQuotes = false;
        $inSingleQuotes = false;
        $result = '';
        $length = strlen($tag);

        for ($index = 0; $index < $length; $index++) {
            $char = $tag[$index];

            if ($char === '"' && ! $inSingleQuotes) {
                $wasInDoubleQuotes = $inDoubleQuotes;
                $inDoubleQuotes = ! $inDoubleQuotes;
                $result .= $char;

                if ($wasInDoubleQuotes && $this->shouldInsertSpaceAfterQuote($tag, $index)) {
                    $result .= ' ';
                }

                continue;
            }

            if ($char === "'" && ! $inDoubleQuotes) {
                $wasInSingleQuotes = $inSingleQuotes;
                $inSingleQuotes = ! $inSingleQuotes;
                $result .= $char;

                if ($wasInSingleQuotes && $this->shouldInsertSpaceAfterQuote($tag, $index)) {
                    $result .= ' ';
                }

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    private function shouldInsertSpaceAfterQuote(string $tag, int $index): bool
    {
        $next = $tag[$index + 1] ?? '';

        if ($next === '' || preg_match('/\s/', $next) === 1) {
            return false;
        }

        if (in_array($next, ['>', '/', '"', "'"], true)) {
            return false;
        }

        return preg_match('/[a-zA-Z:@{\-]/', $next) === 1;
    }

    private function removeDarkUtilities(string $content): string
    {
        $pattern = '/(?<=\s|"|\'|`)(?:[a-z\-]+:)*dark:(?:[a-z\-]+:)*[a-zA-Z0-9\-\/\[\]\:\#\.]+/';
        $updated = preg_replace($pattern, '', $content);

        if ($updated === null) {
            return $content;
        }

        $updated = preg_replace('/ {2,}/', ' ', $updated);

        if ($updated === null) {
            return $content;
        }

        $updated = preg_replace_callback('/class="([^"]*)"/', static function (array $matches): string {
            $classes = preg_split('/\s+/', trim($matches[1])) ?: [];
            $normalizedClasses = implode(' ', array_filter($classes, static fn (string $class): bool => $class !== ''));

            return $normalizedClasses === ''
                ? 'class=""'
                : 'class="'.$normalizedClasses.'"';
        }, $updated);

        if ($updated === null) {
            return $content;
        }

        $updated = str_replace('class=""', '', $updated);

        return $updated ?? $content;
    }

    private function relativePath(string $absolutePath): string
    {
        return Str::of($absolutePath)
            ->after(base_path().DIRECTORY_SEPARATOR)
            ->toString();
    }
}
