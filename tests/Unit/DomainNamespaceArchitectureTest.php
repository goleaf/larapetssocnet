<?php

use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Support\Models\LegacyModelMorphMap;
use Illuminate\Database\Eloquent\Relations\Relation;

it('keeps domain classes out of the root controller request and model folders', function (): void {
    expect(rootPhpFiles(app_path('Http/Controllers')))->toBe([app_path('Http/Controllers/Controller.php')]);
    expect(rootPhpFiles(app_path('Http/Requests')))->toBeEmpty();
    expect(rootPhpFiles(app_path('Models')))->toBeEmpty();
});

it('keeps legacy morph aliases stable after model namespaces move', function (): void {
    LegacyModelMorphMap::register();

    expect(Relation::getMorphedModel('App\Models\Post'))->toBe(Post::class);
    expect(Relation::getMorphedModel('App\Models\User'))->toBe(User::class);
    expect(Relation::getMorphedModel('App\Models\Pet'))->toBe(Pet::class);
    expect(Relation::getMorphedModel('App\Models\Group'))->toBe(Group::class);

    expect((new Post)->getMorphClass())->toBe('App\Models\Post');
    expect((new User)->getMorphClass())->toBe('App\Models\User');
    expect((new Pet)->getMorphClass())->toBe('App\Models\Pet');
    expect((new Group)->getMorphClass())->toBe('App\Models\Group');
});

it('covers every domain model with a legacy morph alias', function (): void {
    $domainModels = collect(domainClasses(app_path('Models')))
        ->sort()
        ->values()
        ->all();

    $mappedModels = collect(LegacyModelMorphMap::aliases())
        ->values()
        ->sort()
        ->values()
        ->all();

    expect(array_values(array_diff($domainModels, $mappedModels)))->toBeEmpty();
});

/**
 * @return list<string>
 */
function rootPhpFiles(string $path): array
{
    return collect(glob($path.'/*.php') ?: [])
        ->sort()
        ->values()
        ->all();
}

/**
 * @return list<class-string>
 */
function domainClasses(string $path): array
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    return collect(iterator_to_array($files))
        ->filter(fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php')
        ->map(fn (SplFileInfo $file): ?string => classFromFile($file->getPathname()))
        ->filter()
        ->values()
        ->all();
}

/**
 * @return class-string|null
 */
function classFromFile(string $path): ?string
{
    $contents = file_get_contents($path);

    if (! is_string($contents)) {
        return null;
    }

    preg_match('/^namespace\s+([^;]+);/m', $contents, $namespace);
    preg_match('/^(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)\b/m', $contents, $class);

    if (! isset($namespace[1], $class[1])) {
        return null;
    }

    /** @var class-string $fqcn */
    $fqcn = $namespace[1].'\\'.$class[1];

    return $fqcn;
}
