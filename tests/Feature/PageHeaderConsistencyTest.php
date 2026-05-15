<?php

use Illuminate\Support\Facades\File;

it('uses the shared page header component for every app layout header slot', function (): void {
    $viewsPath = resource_path('views');
    $excludedFiles = [
        $viewsPath.'/layouts/app.blade.php',
    ];

    $violations = collect(File::allFiles($viewsPath))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->reject(fn (SplFileInfo $file): bool => in_array($file->getPathname(), $excludedFiles, true))
        ->filter(function (SplFileInfo $file): bool {
            $contents = File::get($file->getPathname());

            return str_contains($contents, '<x-slot name="header">')
                && ! str_contains($contents, 'x-ui.page-header');
        })
        ->map(fn (SplFileInfo $file): string => str_replace($viewsPath.'/', '', $file->getPathname()))
        ->values()
        ->all();

    expect($violations)->toBeEmpty(
        'These Blade views still define a header slot without x-ui.page-header: '.implode(', ', $violations)
    );
});
