<?php

use Illuminate\Support\Collection;

it('keeps notifications grouped by provider and domain logic', function (): void {
    $notificationRoot = app_path('Notifications');
    $rootNotificationFiles = glob($notificationRoot.'/*.php') ?: [];
    $notificationFiles = notificationPhpFiles($notificationRoot);
    $allowedProviders = ['Database', 'Mail'];

    $layoutViolations = $notificationFiles
        ->mapWithKeys(function (SplFileInfo $file) use ($notificationRoot, $allowedProviders): array {
            $relativePath = ltrim(str_replace($notificationRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
            $provider = $parts[0] ?? null;
            $domain = $parts[1] ?? null;

            if (count($parts) !== 3 || ! in_array($provider, $allowedProviders, true) || blank($domain)) {
                return [$relativePath => 'Expected app/Notifications/{Database|Mail}/{Domain}/{Class}.php'];
            }

            $contents = file_get_contents($file->getPathname());

            if (! is_string($contents)) {
                return [$relativePath => 'Unreadable notification file'];
            }

            $expectedNamespace = 'namespace App\\Notifications\\'.$provider.'\\'.$domain.';';

            return str_contains($contents, $expectedNamespace)
                ? []
                : [$relativePath => "Missing {$expectedNamespace}"];
        })
        ->all();

    expect($rootNotificationFiles)->toBeEmpty();
    expect($layoutViolations)->toBeEmpty();
});

/**
 * @return Collection<int, SplFileInfo>
 */
function notificationPhpFiles(string $path): Collection
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    return collect(iterator_to_array($files))
        ->filter(fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php')
        ->sortBy(fn (SplFileInfo $file): string => $file->getPathname())
        ->values();
}
