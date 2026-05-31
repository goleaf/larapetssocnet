<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Spatie\MediaLibrary\HasMedia;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;

final class ModelFactoryCoverage
{
    /** @return array<class-string, string> */
    public static function exclusions(): array
    {
        /** @var array<class-string, string> $exclusions */
        $exclusions = require base_path('tests/Support/FactoryCoverageExclusions.php');

        return $exclusions;
    }

    public static function discoverModels(): Collection
    {
        $modelsPath = dirname(__DIR__, 2).'/app/Models';
        $finder = (new Finder)->files()->in($modelsPath)->name('*.php');

        return collect($finder)
            ->map(static function (SplFileInfo $file): ?string {
                $relativeClass = str_replace('.php', '', $file->getRelativePathname());
                $class = 'App\\Models\\'.str_replace('/', '\\', $relativeClass);

                if (! class_exists($class)) {
                    return null;
                }

                return $class;
            })
            ->filter()
            ->filter(static fn (string $class): bool => self::isDiscoverableConcreteModel($class))
            ->sort()
            ->values();
    }

    public static function isDiscoverableConcreteModel(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        if (! is_subclass_of($class, Model::class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return false;
        }

        return ! self::isPivotModel($class);
    }

    public static function isExcludedModel(string $class): bool
    {
        return array_key_exists($class, self::exclusions());
    }

    public static function discoverModelMeta(string $class): array
    {
        $reflection = new ReflectionClass($class);
        /** @var Model $model */
        $model = new $class;

        $table = $model->getTable();

        return [
            'class' => $class,
            'is_eloquent' => $reflection->isSubclassOf(Model::class),
            'is_abstract' => $reflection->isAbstract(),
            'uses_has_factory' => self::usesHasFactory($class),
            'has_factory' => self::hasFactoryMethod($class),
            'table' => $table,
            'has_table' => Schema::hasTable($table),
            'required_fields' => self::requiredFields($model),
            'unique_fields' => self::uniqueFields($table),
            'enum_fields' => self::enumFields($model),
            'status_fields' => self::statusFields($model),
            'uses_soft_deletes' => self::usesSoftDeletes($class),
            'implements_has_media' => self::implementsHasMedia($class),
        ];
    }

    public static function isPivotModel(string $class): bool
    {
        return is_subclass_of($class, Pivot::class) || is_subclass_of($class, MorphPivot::class);
    }

    public static function hasFactoryMethod(string $class): bool
    {
        if (! method_exists($class, 'factory')) {
            return false;
        }

        try {
            $class::factory();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public static function usesHasFactory(string $class): bool
    {
        return in_array(HasFactory::class, class_uses_recursive($class), true);
    }

    public static function usesSoftDeletes(string $class): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($class), true);
    }

    public static function implementsHasMedia(string $class): bool
    {
        if (! interface_exists(HasMedia::class)) {
            return false;
        }

        return in_array(HasMedia::class, class_implements($class), true);
    }

    /** @return list<string> */
    public static function requiredFields(Model $model): array
    {
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        $skip = array_filter(array_unique(array_merge([
            $model->getKeyName(),
            $model->getCreatedAtColumn(),
            $model->getUpdatedAtColumn(),
        ], self::softDeleteColumn($model))));

        return collect(Schema::getColumns($table))
            ->filter(static function (array $column) use ($skip): bool {
                if (! isset($column['name'])) {
                    return false;
                }

                if (in_array($column['name'], $skip, true)) {
                    return false;
                }

                if (($column['auto_increment'] ?? false) === true) {
                    return false;
                }

                if (($column['nullable'] ?? true) === true) {
                    return false;
                }

                return ! array_key_exists('default', $column);
            })
            ->pluck('name')
            ->values()
            ->all();
    }

    /** @return list<string> */
    private static function softDeleteColumn(Model $model): array
    {
        if (! method_exists($model, 'getDeletedAtColumn')) {
            return [];
        }

        /** @var string|null $deletedAtColumn */
        $deletedAtColumn = $model->getDeletedAtColumn();

        return $deletedAtColumn !== null ? [$deletedAtColumn] : [];
    }

    /** @return list<string> */
    public static function uniqueFields(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = [];

        foreach (Schema::getIndexes($table) as $index) {
            if (! (($index['unique'] ?? false) === true)) {
                continue;
            }

            if (($index['primary'] ?? false) === true) {
                continue;
            }

            foreach ((array) ($index['columns'] ?? []) as $column) {
                if (! is_string($column) || in_array($column, $columns, true)) {
                    continue;
                }

                $columns[] = $column;
            }
        }

        return $columns;
    }

    /** @return list<string> */
    public static function enumFields(Model $model): array
    {
        return collect($model->getCasts())
            ->filter(static fn (string|array $cast): bool => is_string($cast) && enum_exists($cast))
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function statusFields(Model $model): array
    {
        $castFields = collect($model->getCasts())
            ->filter(static fn (mixed $_cast, string $name): bool => $name === 'status' || str_ends_with($name, '_status'))
            ->keys();

        if (! Schema::hasTable($model->getTable())) {
            return $castFields->values()->all();
        }

        $columnFields = collect(Schema::getColumns($model->getTable()))
            ->filter(static function (array $column): bool {
                if (! isset($column['name'])) {
                    return false;
                }

                return $column['name'] === 'status' || str_ends_with((string) $column['name'], '_status');
            })
            ->map(static fn (array $column): string => (string) $column['name']);

        return $castFields
            ->merge($columnFields)
            ->unique()
            ->values()
            ->all();
    }
}
