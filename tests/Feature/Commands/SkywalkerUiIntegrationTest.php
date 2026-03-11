<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->workspace = storage_path('framework/testing/skywalker-ui-'.Str::uuid()->toString());
    File::ensureDirectoryExists($this->workspace);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspace);
});

it('registers skywalker ui package commands', function (): void {
    $commands = app(Kernel::class)->all();

    expect($commands)
        ->toHaveKey('ui')
        ->toHaveKey('ui:auth')
        ->toHaveKey('ui:controllers');
});

it('scaffolds isolated skywalker preset files', function (): void {
    $this->artisan('ui', [
        'type' => 'larapets',
        '--option' => ["target={$this->workspace}"],
    ])->assertExitCode(0);

    expect(File::exists($this->workspace.'/preset/sass/_variables.scss'))->toBeTrue()
        ->and(File::exists($this->workspace.'/preset/sass/app.scss'))->toBeTrue()
        ->and(File::exists($this->workspace.'/preset/js/bootstrap.js'))->toBeTrue()
        ->and(File::exists($this->workspace.'/preset/vite.config.js'))->toBeTrue();
});

it('supports auth scaffolding and force overwrites', function (): void {
    $targetFile = $this->workspace.'/preset/js/bootstrap.js';

    File::ensureDirectoryExists(dirname($targetFile));
    File::put($targetFile, '// keep me');

    $this->artisan('ui', [
        'type' => 'larapets',
        '--auth' => true,
        '--option' => ["target={$this->workspace}"],
    ])->assertExitCode(0);

    expect(File::get($targetFile))->toBe('// keep me')
        ->and(File::exists($this->workspace.'/views/auth/login.blade.php'))->toBeTrue()
        ->and(File::exists($this->workspace.'/views/layouts/app.blade.php'))->toBeTrue()
        ->and(File::exists($this->workspace.'/backend-stubs/Auth/LoginController.stub'))->toBeTrue();

    $this->artisan('ui', [
        'type' => 'larapets',
        '--option' => ["target={$this->workspace}", 'force'],
    ])->assertExitCode(0);

    expect(File::get($targetFile))->not->toBe('// keep me');
});
