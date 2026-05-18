#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$options = parseArguments($argv);

if ($options['help']) {
    showHelp();
    exit(0);
}

$controllers = controllerFiles($root);
$tests = testFiles($root);
$map = mapControllersToTests($controllers, $tests, $root);

if ($options['changed']) {
    $changed = changedFiles($root);
    $map = array_values(array_filter($map, static fn (array $item): bool => in_array($item['path'], $changed, true)));
}

$missing = array_values(array_filter($map, static fn (array $item): bool => $item['tests'] === []));

if ($options['format'] === 'json') {
    echo json_encode([
        'controllers' => count($map),
        'missing' => count($missing),
        'items' => $map,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    printReport($map, $missing, $options['changed']);
}

if (($options['strict'] || $options['changed']) && $missing !== []) {
    exit(1);
}

exit(0);

/**
 * @param  array<int, string>  $argv
 * @return array{changed: bool, strict: bool, format: string, help: bool}
 */
function parseArguments(array $argv): array
{
    return [
        'changed' => in_array('--changed', $argv, true),
        'strict' => in_array('--strict', $argv, true),
        'format' => in_array('--format=json', $argv, true) ? 'json' : 'text',
        'help' => in_array('--help', $argv, true) || in_array('-h', $argv, true),
    ];
}

function showHelp(): void
{
    echo <<<'HELP'
Usage:
  php scripts/controller-test-map.php --all
  php scripts/controller-test-map.php --changed
  php scripts/controller-test-map.php --strict
  php scripts/controller-test-map.php --all --format=json

Options:
  --all          Audit every concrete controller. This is the default.
  --changed      Audit controllers changed in the index or working tree.
  --strict       Exit non-zero when any audited controller has no related test.
  --format=json  Emit machine-readable JSON.

HELP;
}

/**
 * @return list<string>
 */
function controllerFiles(string $root): array
{
    $files = phpFiles($root.'/app/Http/Controllers');
    $files = array_filter($files, static function (string $file): bool {
        return str_ends_with($file, 'Controller.php')
            && basename($file) !== 'Controller.php';
    });

    sort($files);

    return array_map(static fn (string $file): string => relativePath($root, $file), array_values($files));
}

/**
 * @return list<string>
 */
function testFiles(string $root): array
{
    $files = array_merge(
        phpFiles($root.'/tests/Feature'),
        phpFiles($root.'/tests/Unit'),
    );

    sort($files);

    return array_values(array_unique(array_map(static fn (string $file): string => relativePath($root, $file), $files)));
}

/**
 * @return list<string>
 */
function phpFiles(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * @param  list<string>  $controllers
 * @param  list<string>  $tests
 * @return list<array{path: string, name: string, area: string, tokens: list<string>, tests: list<string>}>
 */
function mapControllersToTests(array $controllers, array $tests, string $root): array
{
    $testIndex = [];

    foreach ($tests as $test) {
        $testIndex[$test] = searchableText($root, $test);
    }

    return array_map(static function (string $controller) use ($testIndex): array {
        $tokens = controllerTokens($controller);
        $relatedTests = [];

        foreach ($testIndex as $test => $haystack) {
            foreach ($tokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    $relatedTests[] = $test;
                    break;
                }
            }
        }

        return [
            'path' => $controller,
            'name' => basename($controller, '.php'),
            'area' => explode('/', str_replace('app/Http/Controllers/', '', $controller))[0] ?? 'Controllers',
            'tokens' => $tokens,
            'tests' => array_values(array_unique($relatedTests)),
        ];
    }, $controllers);
}

/**
 * @return list<string>
 */
function controllerTokens(string $controller): array
{
    $relative = str_replace('app/Http/Controllers/', '', $controller);
    $parts = explode('/', $relative);
    $name = basename($controller, '.php');
    $stem = preg_replace('/Controller$/', '', $name) ?: $name;
    $words = preg_split('/(?=[A-Z])/', $stem, -1, PREG_SPLIT_NO_EMPTY) ?: [$stem];
    $area = count($parts) > 1 ? $parts[0] : '';

    $tokens = [
        strtolower($name),
        strtolower($stem),
        strtolower($area),
        strtolower(kebab($stem)),
        strtolower(snake($stem)),
        strtolower(kebab($area)),
        strtolower(snake($area)),
    ];

    foreach ($words as $word) {
        $token = strtolower($word);
        $tokens[] = $token;
        $tokens[] = $token.'s';
    }

    $tokens[] = strtolower($stem).'s';
    $tokens[] = strtolower(snake($stem)).'s';
    $tokens[] = strtolower(kebab($stem)).'s';

    return array_values(array_unique(array_filter($tokens, static fn (string $token): bool => strlen($token) >= 4)));
}

function searchableText(string $root, string $relativePath): string
{
    return strtolower($relativePath."\n".((string) file_get_contents($root.'/'.$relativePath)));
}

function kebab(string $value): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(snake($value))), '-');
}

function snake(string $value): string
{
    $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

    return strtolower(str_replace(['-', ' '], '_', $value));
}

/**
 * @return list<string>
 */
function changedFiles(string $root): array
{
    $commands = [
        'git diff --name-only',
        'git diff --cached --name-only',
    ];
    $files = [];

    foreach ($commands as $command) {
        $output = [];
        exec('cd '.escapeshellarg($root).' && '.$command, $output);
        $files = array_merge($files, $output);
    }

    return array_values(array_unique(array_filter($files)));
}

/**
 * @param  list<array{path: string, name: string, area: string, tokens: list<string>, tests: list<string>}>  $map
 * @param  list<array{path: string, name: string, area: string, tokens: list<string>, tests: list<string>}>  $missing
 */
function printReport(array $map, array $missing, bool $changedOnly): void
{
    $scope = $changedOnly ? 'changed controllers' : 'all controllers';

    echo 'Controller test map: '.count($map).' '.$scope.', '.count($missing).' without related tests.'.PHP_EOL;

    if ($map === []) {
        echo 'No controllers found for this scope.'.PHP_EOL;

        return;
    }

    foreach ($missing as $item) {
        echo 'Missing related test: '.$item['path'].PHP_EOL;
    }
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace($root, '', $path), '/');
}
