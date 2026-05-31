<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/app/Models'));
$result = [];

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $relative = str_replace(__DIR__ . '/app/Models' . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $class = 'App\\Models\\' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

    if (! class_exists($class)) {
        continue;
    }

    if (! is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
        continue;
    }

    $ref = new ReflectionClass($class);
    if ($ref->isAbstract()) {
        continue;
    }

    try {
        $instance = new $class();
    } catch (Throwable $e) {
        continue;
    }

    $result[] = [
        'class' => $class,
        'table' => $instance->getTable(),
        'fillable_count' => count($instance->getFillable()),
        'guarded_count' => count($instance->getGuarded()),
        'hasFactory' => in_array(\Illuminate\Database\Eloquent\Factories\HasFactory::class, class_uses_recursive($class), true),
        'hasMedia' => in_array(\Spatie\MediaLibrary\HasMedia::class, class_implements($class), true),
        'casts' => $instance->getCasts(),
    ];
}

usort($result, fn($a, $b) => strcmp($a['class'], $b['class']));
foreach ($result as $item) {
    echo $item['class'], '|', $item['table'], '|factory=', ($item['hasFactory'] ? 'yes' : 'no'), '|media=', ($item['hasMedia'] ? 'yes' : 'no'), '|casts=', json_encode($item['casts']), PHP_EOL;
}
