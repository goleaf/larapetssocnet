<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\HasMedia;
use ReflectionClass;
use ReflectionMethod;

$modelFiles = collect(glob(__DIR__.'/../app/Models/**/*.php', GLOB_BRACE))->sort()->values();
$modelClasses = [];

foreach ($modelFiles as $file) {
    $contents = file_get_contents($file);
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $nsMatch) && preg_match('/class\s+([A-Za-z0-9_]+)/i', $contents, $classMatch)) {
        $fqcn = $nsMatch[1].'\\'.$classMatch[1];
        if (class_exists($fqcn)) {
            $modelClasses[] = $fqcn;
        }
    }
}

$enumClasses = collect(glob(__DIR__.'/../app/Enums/*.php'))->map(function (string $file): ?string {
    $contents = file_get_contents($file);
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $nsMatch) && preg_match('/enum\s+([A-Za-z0-9_]+)/i', $contents, $classMatch)) {
        return $nsMatch[1].'\\'.$classMatch[1];
    }
    return null;
})->filter();

$seeders = collect(glob(__DIR__.'/../database/seeders/*.php'))->filter(fn($f) => basename($f) !== 'DatabaseSeeder.php')->values();
$factoryFiles = collect(glob(__DIR__.'/../database/factories/**/*.php', GLOB_BRACE))->values();
$factoryClasses = [];
foreach ($factoryFiles as $file) {
    $contents = file_get_contents($file);
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $nsMatch) && preg_match('/class\s+([A-Za-z0-9_]+)/i', $contents, $classMatch)) {
        $fqcn = $nsMatch[1].'\\'.$classMatch[1];
        if (class_exists($fqcn)) {
            $factoryClasses[] = $fqcn;
        }
    }
}

$schemaCache = [];
$getSchema = function (string $table) use (&$schemaCache) {
    if (array_key_exists($table, $schemaCache)) {
        return $schemaCache[$table];
    }

    $columns = DB::select("PRAGMA table_info('{$table}');");
    $fks = DB::select("PRAGMA foreign_key_list('{$table}');");

    $indexes = DB::select("PRAGMA index_list('{$table}');");
    $unique = [];
    foreach ($indexes as $idx) {
        if ((int)$idx->unique === 1 && (str_starts_with($idx->name, 'sqlite_autoindex') !== true)) {
            $indexInfo = DB::select("PRAGMA index_info('{$idx->name}');");
            $cols = array_map(fn($c) => $c->name, $indexInfo);
            if ($cols !== []) {
                $unique[] = $cols;
            }
        }
    }

    $schemaCache[$table] = ['columns' => $columns, 'foreign_keys' => $fks, 'unique_indexes' => $unique];
    return $schemaCache[$table];
};

$seedRefersModel = function (string $modelBase) use ($seeders) {
    $modelBaseLower = strtolower($modelBase);
    $needle = $modelBase;
    $found = [];
    foreach ($seeders as $file) {
        $contents = file_get_contents($file);
        if (str_contains($contents, $needle)) {
            $found[] = basename($file, '.php');
            continue;
        }
        if (str_contains($contents, $modelBaseLower)) {
            $found[] = basename($file, '.php');
        }
    }
    return array_values(array_unique($found));
};

$seederMap = [];
foreach ($seeders as $file) {
    $contents = file_get_contents($file);
    foreach ($modelFiles as $modelFile) {
        if (!preg_match('/class\s+([A-Za-z0-9_]+)/i', file_get_contents($modelFile), $m)) {
            continue;
        }
        $base = $m[1];
        $seeder = basename($file, '.php');
        if (str_contains($contents, $base)) {
            $seederMap[$base][] = $seeder;
        }
    }
}

$factoryMap = [];
foreach ($factoryClasses as $factoryClass) {
    $factoryBase = class_basename($factoryClass);
    $modelRef = (string) preg_replace('/Factory$/', '', $factoryBase);
    $factoryMap[$modelRef][] = $factoryClass;
}

$results = [];

foreach ($modelClasses as $modelClass) {
    try {
        $model = new $modelClass();
    } catch (Throwable $e) {
        continue;
    }

    if (!$model instanceof Model) {
        continue;
    }

    $table = method_exists($model, 'getTable') ? $model->getTable() : null;

    $schema = $table ? $getSchema($table) : ['columns' => [], 'foreign_keys' => [], 'unique_indexes' => []];
    $required = [];
    $nullable = [];

    foreach ($schema['columns'] as $col) {
        if ((int)$col->pk === 1) {
            continue;
        }
        if ((int)$col->notnull === 1 && $col->dflt_value === null && !str_starts_with((string) $col->type, 'TIMESTAMP') && $col->type !== '') {
            $required[] = $col->name;
        } else {
            $nullable[] = $col->name;
        }
    }

    $unique = [];
    foreach ($schema['unique_indexes'] as $idxCols) {
        foreach ($idxCols as $c) {
            if (!in_array($c, $unique, true)) {
                $unique[] = $c;
            }
        }
    }

    $foreign = [];
    foreach ($schema['foreign_keys'] as $fk) {
        $foreign[] = [
            'column' => $fk->from,
            'references' => $fk->to,
            'table' => $fk->table,
            'on_update' => $fk->on_update,
            'on_delete' => $fk->on_delete,
        ];
    }

    $casts = $model->getCasts();
    $enumFields = [];
    foreach ($casts as $field => $cast) {
        if (is_string($cast) && str_starts_with($cast, 'array')) {
            continue;
        }
        if (is_string($cast) && $enumClasses->contains($cast)) {
            $enumFields[$field] = $cast;
        } elseif (is_object($cast) && method_exists($cast, 'get')) {
            $enumFields[$field] = 'ObjectCast';
        }
    }

    $relations = [];
    $ref = new ReflectionClass($modelClass);
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $modelClass) {
            continue;
        }
        if ($method->getNumberOfParameters() > 0) {
            continue;
        }
        if ($method->isStatic()) {
            continue;
        }
        $name = $method->getName();
        if (in_array($name, ['getTable', 'toArray', 'scope', 'newQuery', 'newQueryWithoutScope', 'newQueryForRestoration', 'getConnectionName'], true)) {
            continue;
        }

        try {
            $ret = $model->$name();
        } catch (Throwable $e) {
            continue;
        }

        if ($ret instanceof Relation) {
            $relations[] = $name;
        }
    }

    $usesMedia = is_subclass_of($modelClass, HasMedia::class) || in_array(HasMedia::class, class_implements($modelClass));

    $base = class_basename($modelClass);
    $modelFactory = $factoryMap[$base] ?? [];
    $modelSeeders = $seederMap[$base] ?? [];

    $factoryClass = "App\\Database\\Factories\\".$base.'Factory';
    $factoryExists = false;
    $factoryClass = null;
    foreach ($modelFactory as $f) {
        $factoryClass = $f;
        $factoryExists = true;
        break;
    }

    $results[] = [
        'model' => $modelClass,
        'table' => $table,
        'fillable' => $model->getFillable(),
        'guarded' => $model->getGuarded(),
        'casts' => $casts,
        'enum_fields' => $enumFields,
        'required_fields' => $required,
        'nullable_fields' => $nullable,
        'foreign_keys' => $foreign,
        'unique_fields' => $unique,
        'relationships' => $relations,
        'uses_media_library' => $usesMedia,
        'factory_exists' => $factoryExists,
        'factory_class' => $factoryClass,
        'factory_count' => count($modelFactory),
        'seeder_exists' => count($modelSeeders) > 0,
        'seeder_classes' => $modelSeeders,
        'casts_keys' => array_keys($casts),
    ];
}

usort($results, fn($a, $b) => strcmp($a['model'], $b['model']));
file_put_contents('/tmp/model_inventory.json', json_encode($results, JSON_PRETTY_PRINT));

// table stats
$allTables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") );
$tables = [];
foreach ($allTables as $row) {
    $name = $row->name;
    $columns = DB::select("PRAGMA table_info('{$name}');");
    $columns = array_map(function ($c) {
        return [
            'name' => $c->name,
            'type' => $c->type,
            'notnull' => (int) $c->notnull,
            'dflt_value' => $c->dflt_value,
            'pk' => (int) $c->pk,
        ];
    }, $columns);
    $fks = DB::select("PRAGMA foreign_key_list('{$name}');");
    $fks = array_map(function ($fk) {
        return ['id'=>$fk->id,'seq'=>$fk->seq,'table'=>$fk->table,'from'=>$fk->from,'to'=>$fk->to,'on_update'=>$fk->on_update,'on_delete'=>$fk->on_delete];
    }, $fks);
    $tables[] = ['name'=>$name,'columns'=>$columns,'foreign_keys'=>$fks];
}
file_put_contents('/tmp/schema_tables.json', json_encode($tables, JSON_PRETTY_PRINT));

// enums
$enumFiles = glob(__DIR__.'/../app/Enums/*.php');
$enums = [];
foreach ($enumFiles as $file) {
    $contents = file_get_contents($file);
    if (!preg_match('/namespace\s+([^;]+);/i', $contents, $nsMatch) || !preg_match('/enum\s+([A-Za-z0-9_]+)/i', $contents, $classMatch)) {
        continue;
    }
    $fqcn = $nsMatch[1].'\\'.$classMatch[1];
    if (!class_exists($fqcn)) {
        continue;
    }

    $rc = new ReflectionClass($fqcn);
    $cases = [];
    foreach ($rc->getReflectionConstants() as $const) {
        if (!$const->isPublic()) {
            continue;
        }
        $cases[] = $const->getName();
    }
    $enums[] = ['enum'=>$fqcn,'cases'=>$cases];
}
file_put_contents('/tmp/enums.json', json_encode($enums, JSON_PRETTY_PRINT));

// policies
$policies = collect(glob(__DIR__.'/../app/Policies/*.php'))->map(function ($file) {
    $contents = file_get_contents($file);
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $nsMatch) && preg_match('/class\s+([A-Za-z0-9_]+)/i', $contents, $classMatch)) {
        return $nsMatch[1].'\\'.$classMatch[1];
    }
    return null;
})->filter()->values()->all();
file_put_contents('/tmp/policies.json', json_encode($policies, JSON_PRETTY_PRINT));

// observers
$observers = collect(glob(__DIR__.'/../app/Observers/*.php'))->map(function ($file) {
    $contents = file_get_contents($file);
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $nsMatch) && preg_match('/class\s+([A-Za-z0-9_]+)/i', $contents, $classMatch)) {
        return $nsMatch[1].'\\'.$classMatch[1];
    }
    return null;
})->filter()->values()->all();
file_put_contents('/tmp/observers.json', json_encode($observers, JSON_PRETTY_PRINT));

// seeders details simple
$seedersOut = [];
foreach ($seeders as $file) {
    $contents = file_get_contents($file);
    $name = basename($file, '.php');
    preg_match('/namespace\s+([^;]+);/i', $contents, $n);
    preg_match('/class\s+([A-Za-z0-9_]+)/i', $contents, $c);
    $seedersOut[] = [
        'seeder' => $n[1].'\\'.$c[1],
        'file' => $name,
        'creates_model_calls' => (int) preg_match_all('/create\(/', $contents),
        'create_many_calls' => (int) preg_match_all('/createMany\(/', $contents),
        'references_model' => collect($modelFiles)->reduce(function ($carry, $mf) use ($contents) {
            if (!preg_match('/class\s+([A-Za-z0-9_]+)/i', file_get_contents($mf), $m)) {
                return $carry;
            }
            $base = $m[1];
            if (str_contains($contents, $base)) {
                $carry[] = 'App\\Models\\'.str_replace('/', '\\', ltrim(str_replace(dirname($mf)."/", '', $mf), '/')).'';
            }
            return $carry;
        }, []),
        'truncates_users' => (stripos($contents, 'truncate(') !== false || stripos($contents, 'DB::statement("DELETE') !== false || str_contains($contents, '->truncate()')),
        'uses_factory' => (stripos($contents, 'factory(') !== false || stripos($contents, 'Factory::') !== false || stripos($contents, '->factory(') !== false),
    ];
}
file_put_contents('/tmp/seeders_inventory.json', json_encode($seedersOut, JSON_PRETTY_PRINT));

// file/disks
$filesystems = config('filesystems.disks');
$mediaLibrary = config('media-library');
$livewireTmp = config('livewire.temporary_file_upload', []);
file_put_contents('/tmp/filesystems_snapshot.json', json_encode(['filesystems'=>$filesystems,'media_library'=>$mediaLibrary,'livewire'=>$livewireTmp], JSON_PRETTY_PRINT));

