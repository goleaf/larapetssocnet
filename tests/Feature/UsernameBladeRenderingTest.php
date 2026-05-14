<?php

use Illuminate\Support\Facades\File;

it('does not escape username blade expressions in views', function (): void {
    $viewsPath = resource_path('views').DIRECTORY_SEPARATOR;

    $offendingFiles = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->filter(function ($file): bool {
            $content = File::get($file->getPathname());

            return preg_match('/@\{\{\s*\$[^}]*username[^}]*\}\}/', $content) === 1;
        })
        ->map(fn ($file): string => str_replace($viewsPath, '', $file->getPathname()))
        ->values()
        ->all();

    expect($offendingFiles)->toBe([]);
});
