<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/ftp-upload.php <local-dir> <remote-dir>\n");
    exit(2);
}

[$script, $localRoot, $remoteRoot] = $argv;
$localRoot = rtrim(realpath($localRoot) ?: $localRoot, DIRECTORY_SEPARATOR);
$remoteRoot = trim($remoteRoot, '/');

if (! is_dir($localRoot)) {
    fwrite(STDERR, "Local directory does not exist: {$localRoot}\n");
    exit(2);
}

$host = getenv('FTP_HOST') ?: '';
$username = getenv('FTP_USERNAME') ?: '';
$password = getenv('FTP_PASSWORD') ?: '';
$protocol = strtolower(getenv('FTP_PROTOCOL') ?: 'ftp');
$port = (int) (getenv('FTP_PORT') ?: ($protocol === 'ftps' ? 21 : 21));

if ($host === '' || $username === '' || $password === '') {
    fwrite(STDERR, "FTP_HOST, FTP_USERNAME, and FTP_PASSWORD are required.\n");
    exit(2);
}

$ftp = $protocol === 'ftps'
    ? ftp_ssl_connect($host, $port, 30)
    : ftp_connect($host, $port, 30);

if ($ftp === false || ! ftp_login($ftp, $username, $password)) {
    fwrite(STDERR, "Unable to connect to FTP server.\n");
    exit(1);
}

ftp_pasv($ftp, true);

$knownRemoteDirectories = ['' => true];

function ensureRemoteDirectory(FTP\Connection $ftp, string $directory): void
{
    global $knownRemoteDirectories;

    $directory = trim($directory, '/');

    if (isset($knownRemoteDirectories[$directory])) {
        return;
    }

    $path = '';

    foreach (explode('/', $directory) as $part) {
        if ($part === '') {
            continue;
        }

        $path .= "/{$part}";
        $cacheKey = trim($path, '/');

        if (isset($knownRemoteDirectories[$cacheKey])) {
            continue;
        }

        if (@ftp_chdir($ftp, $path)) {
            $knownRemoteDirectories[$cacheKey] = true;

            continue;
        }

        if (! @ftp_mkdir($ftp, $path) && ! @ftp_chdir($ftp, $path)) {
            throw new RuntimeException("Unable to create remote directory: {$path}");
        }

        $knownRemoteDirectories[$cacheKey] = true;
    }

    ftp_chdir($ftp, '/');
    $knownRemoteDirectories[$directory] = true;
}

ensureRemoteDirectory($ftp, $remoteRoot);

$files = [];
$directories = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($localRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

foreach ($iterator as $item) {
    $path = $item->getPathname();
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($localRoot) + 1));

    if ($item->isDir()) {
        $directories[] = $relative;
    } elseif ($item->isFile()) {
        $files[] = [$path, $relative];
    }
}

sort($directories);
usort($files, fn (array $a, array $b): int => strcmp($a[1], $b[1]));

foreach ($directories as $directory) {
    ensureRemoteDirectory($ftp, "{$remoteRoot}/{$directory}");
}

$uploaded = 0;
$total = count($files);

foreach ($files as [$path, $relative]) {
    $remotePath = "{$remoteRoot}/{$relative}";

    ensureRemoteDirectory($ftp, dirname($remotePath));

    if (! ftp_put($ftp, $remotePath, $path, FTP_BINARY)) {
        throw new RuntimeException("Unable to upload: {$relative}");
    }

    $uploaded++;

    if ($uploaded % 250 === 0 || $uploaded === $total) {
        echo "Uploaded {$uploaded}/{$total}\n";
        flush();
    }
}

@ftp_delete($ftp, "{$remoteRoot}/index.html");
@ftp_delete($ftp, "{$remoteRoot}/.deploy-probe.php");

ftp_close($ftp);

echo "Upload complete: {$uploaded} files\n";
