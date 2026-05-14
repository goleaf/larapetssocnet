<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

it('can assert streamed content for binary file downloads', function (): void {
    $suffix = bin2hex(random_bytes(6));
    $uri = '/_test/binary-download-'.$suffix;
    $path = storage_path('framework/testing/binary-download-'.$suffix.'.txt');
    $content = 'laravel-binary-file-response-content';

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $content);

    Route::get($uri, fn () => response()->download($path, 'export.txt', [
        'Content-Type' => 'text/plain',
    ]));

    try {
        $this->get($uri)
            ->assertOk()
            ->assertDownload('export.txt')
            ->assertStreamedContent($content);
    } finally {
        File::delete($path);
    }
});

it('can assert streamed content for inline binary file responses', function (): void {
    $suffix = bin2hex(random_bytes(6));
    $uri = '/_test/binary-inline-'.$suffix;
    $path = storage_path('framework/testing/binary-inline-'.$suffix.'.txt');
    $content = 'laravel-binary-inline-response-content';

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $content);

    Route::get($uri, fn () => response()->file($path, [
        'Content-Type' => 'text/plain',
    ]));

    try {
        $this->get($uri)
            ->assertOk()
            ->assertStreamedContent($content);
    } finally {
        File::delete($path);
    }
});
