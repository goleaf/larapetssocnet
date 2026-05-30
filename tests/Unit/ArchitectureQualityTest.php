<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

it('keeps application code free of debugging output helpers', function (): void {
    $violations = qualityPhpFiles([
        app_path(),
        base_path('routes'),
        database_path('factories'),
        database_path('seeders'),
    ])
        ->filter(fn (string $path): bool => preg_match('/\b(dd|dump|ray|var_dump|print_r)\s*\(/', qualitySource($path)) === 1)
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('prevents accidental lazy loading outside production', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('keeps environment access in configuration files', function (): void {
    $violations = qualityPhpFiles([
        app_path(),
        base_path('routes'),
        database_path('factories'),
        database_path('seeders'),
    ])
        ->filter(fn (string $path): bool => preg_match('/\benv\s*\(/', qualitySource($path)) === 1)
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('uses semantic response assertions in tests for common http statuses', function (): void {
    $violations = qualityPhpFiles([base_path('tests')])
        ->filter(fn (string $path): bool => preg_match('/->assertStatus\((?:200|403|404|422)\)/', qualitySource($path)) === 1)
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('keeps tests free of placeholder truth assertions', function (): void {
    $violations = qualityPhpFiles([base_path('tests')])
        ->filter(fn (string $path): bool => preg_match('/assertTrue\(true\)|expect\(true\)->toBeTrue\(\)/', qualitySource($path)) === 1)
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('keeps tests free of starter-template comments', function (): void {
    $starterComment = implode('', ['A basic test', ' example.']);

    $violations = qualityPhpFiles([base_path('tests')])
        ->filter(fn (string $path): bool => str_contains(qualitySource($path), $starterComment))
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('keeps the test suite free of focused or skipped tests', function (): void {
    $violations = qualityPhpFiles([base_path('tests')])
        ->filter(fn (string $path): bool => preg_match('/(?:->only\(|\bonly\(|->skip\(|\bskip\(|\btodo\()/', qualitySource($path)) === 1)
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('keeps application console command classes in the commands folder', function (): void {
    $consolePath = app_path('Console');

    if (! is_dir($consolePath)) {
        expect($consolePath)->not->toBeDirectory();

        return;
    }

    $rootConsoleFiles = glob($consolePath.'/*.php') ?: [];
    $commandFiles = qualityPhpFiles([$consolePath])->all();
    $commandFileViolations = collect($commandFiles)
        ->reject(fn (string $path): bool => str_starts_with($path, $consolePath.'/Commands/') && str_ends_with($path, 'Command.php'))
        ->values()
        ->all();

    expect($rootConsoleFiles)->toBeEmpty();
    expect($commandFileViolations)->toBeEmpty();
});

it('keeps application background side effects out of an app jobs folder', function (): void {
    expect(app_path('Jobs'))->not->toBeDirectory();
});

it('requires queued application classes to define retry timeout and backoff controls', function (): void {
    $runtimeSource = qualitySource(app_path('Support/Queue/HasDefaultQueueRuntime.php'));

    expect($runtimeSource)
        ->toContain('public int $tries')
        ->toContain('public int $timeout')
        ->toContain('public bool $failOnTimeout')
        ->toContain('function backoff')
        ->toContain('function failed')
        ->toContain('Queued job failed after all retry attempts.');

    $violations = qualityPhpFiles([app_path()])
        ->filter(fn (string $path): bool => str_contains(qualitySource($path), 'ShouldQueue'))
        ->filter(function (string $path): bool {
            $source = qualitySource($path);

            if (str_contains($source, 'HasDefaultQueueRuntime')) {
                return false;
            }

            return ! str_contains($source, '$tries')
                || ! str_contains($source, '$timeout')
                || ! str_contains($source, '$failOnTimeout')
                || ! (str_contains($source, '$backoff') || str_contains($source, 'function backoff'));
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('routes queued application classes onto explicit named queues', function (): void {
    $queueNameSource = qualitySource(app_path('Enums/Support/Queue/QueueName.php'));

    expect($queueNameSource)
        ->toContain("case Mail = 'mail'")
        ->toContain("case Notifications = 'notifications'")
        ->toContain("case Comments = 'comments'")
        ->toContain("case Default = 'default'")
        ->toContain('function priority')
        ->toContain('function workerOrder');

    $violations = qualityPhpFiles([app_path()])
        ->filter(fn (string $path): bool => str_contains(qualitySource($path), 'ShouldQueue'))
        ->filter(function (string $path): bool {
            $source = qualitySource($path);

            return ! str_contains($source, 'QueueName::')
                || ! (str_contains($source, 'onQueue(') || str_contains($source, 'viaQueues('));
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('keeps controllers requests and models in domain folders', function (): void {
    $rootControllerFiles = glob(app_path('Http/Controllers/*.php')) ?: [];
    $rootRequestFiles = glob(app_path('Http/Requests/*.php')) ?: [];
    $rootModelFiles = glob(app_path('Models/*.php')) ?: [];

    expect($rootControllerFiles)->toBe([app_path('Http/Controllers/Controller.php')]);
    expect($rootRequestFiles)->toBeEmpty();
    expect($rootModelFiles)->toBeEmpty();
});

it('uses conventional suffixes for controllers and form requests', function (): void {
    $controllerViolations = qualityPhpFiles([app_path('Http/Controllers')])
        ->reject(fn (string $path): bool => str_ends_with($path, '/Controller.php'))
        ->filter(fn (string $path): bool => ! str_ends_with($path, 'Controller.php'))
        ->values()
        ->all();

    $requestViolations = qualityPhpFiles([app_path('Http/Requests')])
        ->filter(fn (string $path): bool => ! str_ends_with($path, 'Request.php'))
        ->values()
        ->all();

    expect($controllerViolations)->toBeEmpty();
    expect($requestViolations)->toBeEmpty();
});

/**
 * @param  list<string>  $paths
 * @return Collection<int, string>
 */
function qualityPhpFiles(array $paths): Collection
{
    return collect($paths)
        ->filter(fn (string $path): bool => is_dir($path))
        ->flatMap(function (string $path): array {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            return collect(iterator_to_array($files))
                ->filter(fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php')
                ->map(fn (SplFileInfo $file): string => $file->getPathname())
                ->all();
        })
        ->sort()
        ->values();
}

function qualitySource(string $path): string
{
    return (string) file_get_contents($path);
}
