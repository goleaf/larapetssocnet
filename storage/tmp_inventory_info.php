<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$default = config('database.default');
$driver = config("database.connections.$default.driver");
$database = config("database.connections.$default.database");

echo "default=$default\n";
echo "driver=$driver\n";
echo "database=$database\n";

try {
    $pdo = DB::connection()->getPdo();
    echo 'connected=yes\n';
    $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    echo 'tables='.count($tables)."\n";
    $shown = 0;
    foreach ($tables as $t) {
        if ($shown++ >= 5) { break; }
        echo $t->name."\n";
    }
} catch (Throwable $e) {
    echo 'connected=no: '.$e->getMessage()."\n";
}
