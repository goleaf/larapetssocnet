<?php

declare(strict_types=1);

$tokenHash = '__DEPLOY_TOKEN_SHA256__';
$archiveRelativePath = '__DEPLOY_ARCHIVE_RELATIVE_PATH__';
$preserveSqlite = __DEPLOY_PRESERVE_SQLITE__;
$targetRoot = __DIR__;

header('Content-Type: application/json');

set_time_limit(600);

function deploy_json_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;

    exit;
}

function deploy_normalize_path(string $path): string
{
    return trim(str_replace('\\', '/', $path), '/');
}

function deploy_relative_path(string $basePath, string $path): string
{
    $relativePath = substr($path, strlen($basePath));

    return deploy_normalize_path($relativePath === false ? $path : $relativePath);
}

function deploy_is_sqlite_database(string $relativePath): bool
{
    return $relativePath === 'laravel/database/database.sqlite'
        || str_starts_with($relativePath, 'laravel/database/database.sqlite-');
}

function deploy_should_preserve(string $relativePath, string $archiveRelativePath, bool $preserveSqlite): bool
{
    $relativePath = deploy_normalize_path($relativePath);

    if ($relativePath === '') {
        return true;
    }

    if (in_array($relativePath, [
        '.htaccess',
        '__ftp_deploy.php',
        'laravel/.htaccess',
        $archiveRelativePath,
        'laravel/storage',
        'storage',
    ], true)) {
        return true;
    }

    if (str_starts_with($relativePath, 'laravel/storage/')
        || str_starts_with($relativePath, 'storage/')) {
        return true;
    }

    return $preserveSqlite && deploy_is_sqlite_database($relativePath);
}

function deploy_is_empty_directory(string $path): bool
{
    $entries = scandir($path);

    if ($entries === false) {
        throw new RuntimeException("Unable to inspect directory: {$path}");
    }

    return count(array_diff($entries, ['.', '..'])) === 0;
}

function deploy_remove_path(string $path): void
{
    if (is_dir($path) && ! is_link($path)) {
        $entries = scandir($path);

        if ($entries === false) {
            throw new RuntimeException("Unable to inspect directory: {$path}");
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            deploy_remove_path($path.DIRECTORY_SEPARATOR.$entry);
        }

        if (! rmdir($path)) {
            throw new RuntimeException("Unable to remove directory: {$path}");
        }

        return;
    }

    if (file_exists($path) && ! unlink($path)) {
        throw new RuntimeException("Unable to remove file: {$path}");
    }
}

function deploy_clean_directory(string $baseRoot, string $currentDirectory, string $archiveRelativePath, bool $preserveSqlite): int
{
    $entries = scandir($currentDirectory);

    if ($entries === false) {
        throw new RuntimeException("Unable to inspect deployment root: {$currentDirectory}");
    }

    $removed = 0;

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $currentDirectory.DIRECTORY_SEPARATOR.$entry;
        $relativePath = deploy_relative_path($baseRoot, $path);

        if (deploy_should_preserve($relativePath, $archiveRelativePath, $preserveSqlite)) {
            continue;
        }

        if (is_dir($path) && ! is_link($path)) {
            $removed += deploy_clean_directory($baseRoot, $path, $archiveRelativePath, $preserveSqlite);

            if (! deploy_should_preserve($relativePath, $archiveRelativePath, $preserveSqlite)
                && deploy_is_empty_directory($path)) {
                if (! rmdir($path)) {
                    throw new RuntimeException("Unable to remove directory: {$path}");
                }

                $removed++;
            }

            continue;
        }

        deploy_remove_path($path);
        $removed++;
    }

    return $removed;
}

function deploy_extract_archive(string $archivePath, string $targetRoot): string
{
    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive;
        $result = $zip->open($archivePath);

        if ($result !== true) {
            throw new RuntimeException("Unable to open deployment archive with ZipArchive: {$result}");
        }

        if (! $zip->extractTo($targetRoot)) {
            $zip->close();

            throw new RuntimeException('Unable to extract deployment archive with ZipArchive.');
        }

        $zip->close();

        return 'ziparchive';
    }

    if (class_exists(PharData::class)) {
        try {
            $archive = new PharData($archivePath);
            $archive->extractTo($targetRoot, null, true);

            return 'phardata';
        } catch (Throwable $throwable) {
            throw new RuntimeException('Unable to extract deployment archive with PharData: '.$throwable->getMessage(), 0, $throwable);
        }
    }

    if (function_exists('exec')) {
        $command = 'unzip -oq '.escapeshellarg($archivePath).' -d '.escapeshellarg($targetRoot).' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            return 'unzip';
        }

        throw new RuntimeException('Unable to extract deployment archive with unzip: '.implode("\n", $output));
    }

    throw new RuntimeException('No supported archive extractor is available on the server.');
}

function deploy_reset_opcode_cache(): bool
{
    if (! function_exists('opcache_reset')) {
        return false;
    }

    return @opcache_reset();
}

$archiveRelativePath = deploy_normalize_path($archiveRelativePath);
$archivePath = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archiveRelativePath);
$cleanupArtifacts = false;

register_shutdown_function(static function () use (&$cleanupArtifacts, $archivePath): void {
    if (! $cleanupArtifacts) {
        return;
    }

    if (is_file($archivePath)) {
        @unlink($archivePath);
    }

    if (is_file(__FILE__)) {
        @unlink(__FILE__);
    }
});

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    deploy_json_response(405, ['ok' => false, 'error' => 'POST required.']);
}

$submittedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

if (! is_string($submittedToken) || ! hash_equals($tokenHash, hash('sha256', $submittedToken))) {
    deploy_json_response(403, ['ok' => false, 'error' => 'Invalid deployment token.']);
}

$cleanupArtifacts = true;

if (! is_file($archivePath)) {
    deploy_json_response(500, ['ok' => false, 'error' => 'Deployment archive is missing.']);
}

try {
    $removed = deploy_clean_directory($targetRoot, $targetRoot, $archiveRelativePath, $preserveSqlite);
    $extractor = deploy_extract_archive($archivePath, $targetRoot);
    $opcacheReset = deploy_reset_opcode_cache();

    deploy_json_response(200, [
        'ok' => true,
        'removed' => $removed,
        'extractor' => $extractor,
        'preserved_sqlite' => $preserveSqlite,
        'opcache_reset' => $opcacheReset,
    ]);
} catch (Throwable $throwable) {
    deploy_json_response(500, [
        'ok' => false,
        'error' => $throwable->getMessage(),
    ]);
}
