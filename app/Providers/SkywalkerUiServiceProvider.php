<?php

namespace App\Providers;

use App\Actions\Ui\InstallSkywalkerUiPresetAction;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Skywalker\Ui\Console\Commands\AuthCommand;
use Skywalker\Ui\Console\Commands\ControllersCommand;
use Skywalker\Ui\Console\Commands\UiCommand;

class SkywalkerUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists(UiCommand::class)) {
            return;
        }

        $this->commands([
            UiCommand::class,
            AuthCommand::class,
            ControllersCommand::class,
        ]);
    }

    public function boot(InstallSkywalkerUiPresetAction $installer): void
    {
        if (! class_exists(UiCommand::class) || UiCommand::hasMacro('larapets')) {
            return;
        }

        UiCommand::macro('larapets', function (UiCommand $command) use ($installer): int {
            $options = collect((array) $command->option('option'))
                ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '');

            $targetOption = $options
                ->first(static fn (string $value): bool => Str::startsWith($value, 'target='));

            $targetRoot = is_string($targetOption)
                ? Str::after($targetOption, 'target=')
                : null;

            $force = $options->contains('force');

            return $installer->handle(
                command: $command,
                includeAuth: (bool) $command->option('auth'),
                force: $force,
                targetRoot: $targetRoot,
            );
        });
    }
}
