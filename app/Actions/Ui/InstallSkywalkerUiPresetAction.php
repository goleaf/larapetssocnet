<?php

namespace App\Actions\Ui;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallSkywalkerUiPresetAction
{
    public function __construct(private readonly Filesystem $files) {}

    public function handle(
        Command $command,
        bool $includeAuth = false,
        bool $force = false,
        ?string $targetRoot = null,
    ): int {
        $packagePath = base_path('vendor/skywalker-labs/ui');

        if (! $this->files->isDirectory($packagePath)) {
            $command->error('skywalker-labs/ui is not installed. Run composer require skywalker-labs/ui first.');

            return Command::FAILURE;
        }

        $targetDirectory = $this->resolveTargetDirectory($targetRoot);
        $writtenFiles = [];
        $skippedFiles = [];

        $this->copyFiles(
            stubMap: $this->frontendStubMap($packagePath),
            targetDirectory: $targetDirectory,
            force: $force,
            writtenFiles: $writtenFiles,
            skippedFiles: $skippedFiles,
        );

        if ($includeAuth) {
            $this->copyFiles(
                stubMap: $this->authStubMap($packagePath),
                targetDirectory: $targetDirectory,
                force: $force,
                writtenFiles: $writtenFiles,
                skippedFiles: $skippedFiles,
            );
        }

        $this->renderSummary(
            command: $command,
            targetDirectory: $targetDirectory,
            includeAuth: $includeAuth,
            writtenFiles: $writtenFiles,
            skippedFiles: $skippedFiles,
        );

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function frontendStubMap(string $packagePath): array
    {
        return [
            $packagePath.'/src/Presets/bootstrap-stubs/_variables.scss' => 'preset/sass/_variables.scss',
            $packagePath.'/src/Presets/bootstrap-stubs/app.scss' => 'preset/sass/app.scss',
            $packagePath.'/src/Presets/bootstrap-stubs/bootstrap.js' => 'preset/js/bootstrap.js',
            $packagePath.'/src/Presets/bootstrap-stubs/vite.config.js' => 'preset/vite.config.js',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authStubMap(string $packagePath): array
    {
        return [
            $packagePath.'/src/Auth/bootstrap-stubs/auth/login.stub' => 'views/auth/login.blade.php',
            $packagePath.'/src/Auth/bootstrap-stubs/auth/passwords/confirm.stub' => 'views/auth/passwords/confirm.blade.php',
            $packagePath.'/src/Auth/bootstrap-stubs/auth/passwords/email.stub' => 'views/auth/passwords/email.blade.php',
            $packagePath.'/src/Auth/bootstrap-stubs/auth/passwords/reset.stub' => 'views/auth/passwords/reset.blade.php',
            $packagePath.'/src/Auth/bootstrap-stubs/auth/register.stub' => 'views/auth/register.blade.php',
            $packagePath.'/src/Auth/bootstrap-stubs/auth/verify.stub' => 'views/auth/verify.blade.php',
            $packagePath.'/src/Auth/bootstrap-stubs/home.stub' => 'views/home.blade.php',
            $packagePath.'/src/Auth/bootstrap-stubs/layouts/app.stub' => 'views/layouts/app.blade.php',
            $packagePath.'/stubs/Auth/ConfirmPasswordController.stub' => 'backend-stubs/Auth/ConfirmPasswordController.stub',
            $packagePath.'/stubs/Auth/ForgotPasswordController.stub' => 'backend-stubs/Auth/ForgotPasswordController.stub',
            $packagePath.'/stubs/Auth/LoginController.stub' => 'backend-stubs/Auth/LoginController.stub',
            $packagePath.'/stubs/Auth/RegisterController.stub' => 'backend-stubs/Auth/RegisterController.stub',
            $packagePath.'/stubs/Auth/ResetPasswordController.stub' => 'backend-stubs/Auth/ResetPasswordController.stub',
            $packagePath.'/stubs/Auth/VerificationController.stub' => 'backend-stubs/Auth/VerificationController.stub',
            $packagePath.'/stubs/Auth/controllers/Controller.stub' => 'backend-stubs/Auth/controllers/Controller.stub',
            $packagePath.'/stubs/Auth/controllers/HomeController.stub' => 'backend-stubs/Auth/controllers/HomeController.stub',
            $packagePath.'/stubs/Auth/routes.stub' => 'backend-stubs/Auth/routes.stub',
            $packagePath.'/stubs/migrations/2014_10_12_100000_create_password_resets_table.php' => 'backend-stubs/migrations/2014_10_12_100000_create_password_resets_table.php',
        ];
    }

    /**
     * @param  array<string, string>  $stubMap
     * @param  array<int, string>  $writtenFiles
     * @param  array<int, string>  $skippedFiles
     */
    private function copyFiles(
        array $stubMap,
        string $targetDirectory,
        bool $force,
        array &$writtenFiles,
        array &$skippedFiles,
    ): void {
        foreach ($stubMap as $source => $relativeTarget) {
            if (! $this->files->exists($source)) {
                continue;
            }

            $destination = $targetDirectory.DIRECTORY_SEPARATOR.$relativeTarget;

            $this->files->ensureDirectoryExists(dirname($destination));

            if ($this->files->exists($destination) && ! $force) {
                $skippedFiles[] = $relativeTarget;

                continue;
            }

            $this->files->copy($source, $destination);
            $writtenFiles[] = $relativeTarget;
        }
    }

    /**
     * @param  array<int, string>  $writtenFiles
     * @param  array<int, string>  $skippedFiles
     */
    private function renderSummary(
        Command $command,
        string $targetDirectory,
        bool $includeAuth,
        array $writtenFiles,
        array $skippedFiles,
    ): void {
        if ($writtenFiles !== []) {
            $command->info('Skywalker UI scaffolding prepared in an isolated directory.');
            $command->line('Created: '.count($writtenFiles));
        }

        if ($skippedFiles !== []) {
            $command->warn(count($skippedFiles).' existing file(s) were skipped. Use --option=force to overwrite.');
        }

        if ($writtenFiles === [] && $skippedFiles === []) {
            $command->warn('No files were generated.');
        }

        $command->line('Target: '.$targetDirectory);
        $command->line('Auth stubs: '.($includeAuth ? 'yes' : 'no'));
    }

    private function resolveTargetDirectory(?string $targetRoot): string
    {
        $normalizedTargetRoot = trim((string) $targetRoot);

        if ($normalizedTargetRoot === '') {
            return resource_path('skywalker-ui');
        }

        if (Str::startsWith($normalizedTargetRoot, ['/'])) {
            return $normalizedTargetRoot;
        }

        return base_path($normalizedTargetRoot);
    }
}
