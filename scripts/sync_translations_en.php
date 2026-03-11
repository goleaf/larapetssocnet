<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$langFile = $base.'/lang/en.php';
$targets = [
    $base.'/resources/views',
    $base.'/app',
];

$existing = [];
if (is_file($langFile)) {
    $loaded = require $langFile;
    if (is_array($loaded)) {
        $existing = $loaded;
    }
}

$keyToValue = $existing;
$valueToKey = [];
foreach ($keyToValue as $key => $value) {
    if (is_string($value)) {
        $valueToKey[$value] = (string) $key;
    }
}

$usedKeys = array_fill_keys(array_keys($keyToValue), true);

$makeKey = static function (string $value) use (&$usedKeys): string {
    $key = strtolower($value);
    $key = preg_replace('/:[a-zA-Z_][a-zA-Z0-9_]*/', 'param', $key) ?? $key;
    $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
    $key = trim($key, '_');

    if ($key === '') {
        $key = 'text';
    }

    if (strlen($key) > 80) {
        $key = substr($key, 0, 80);
        $key = rtrim($key, '_');
    }

    $candidate = $key;
    $index = 2;

    while (isset($usedKeys[$candidate])) {
        $candidate = $key.'_'.$index;
        $index++;
    }

    $usedKeys[$candidate] = true;

    return $candidate;
};

$getKey = static function (string $value) use (&$valueToKey, &$keyToValue, $makeKey): string {
    if (isset($valueToKey[$value])) {
        return $valueToKey[$value];
    }

    $key = $makeKey($value);
    $valueToKey[$value] = $key;
    $keyToValue[$key] = $value;

    return $key;
};

$patterns = [
    "/__\\(\\s*'((?:\\\\.|[^'])*)'\\s*([,\\)])/",
    '/__\\(\\s*"((?:\\\\.|[^\"])*)"\\s*([,\\)])/',
];

$changedFiles = 0;

$iterators = [];
foreach ($targets as $target) {
    if (is_dir($target)) {
        $iterators[] = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
    }
}

foreach ($iterators as $iterator) {
    foreach ($iterator as $file) {
        $path = $file->getPathname();

        if (! preg_match('/\\.(php|blade\\.php)$/', $path)) {
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $original = $content;

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback($pattern, static function (array $matches) use ($getKey): string {
                $raw = stripcslashes($matches[1]);

                if ($raw === '') {
                    return $matches[0];
                }

                if (preg_match('/^[a-z0-9_]+(?:\\.[a-z0-9_]+)+$/i', $raw)) {
                    return $matches[0];
                }

                $key = $getKey($raw);

                return "__('en.".$key."'".$matches[2];
            }, $content) ?? $content;
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
            $changedFiles++;
        }
    }
}

ksort($keyToValue);
$export = "<?php\n\nreturn ".var_export($keyToValue, true).";\n";
$export = preg_replace('/^  /m', '    ', $export) ?? $export;
file_put_contents($langFile, $export);

echo "Updated {$changedFiles} files\n";
echo 'Total keys: '.count($keyToValue)."\n";
