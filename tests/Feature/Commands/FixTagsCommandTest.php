<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->workspace = storage_path('framework/testing/fix-tags-command-'.Str::uuid()->toString());
    File::ensureDirectoryExists($this->workspace);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspace);
});

it('fixes malformed adjacent blade attributes', function (): void {
    $file = $this->workspace.'/sample.blade.php';

    File::put($file, '<input type="text"name="pet_name"required>');

    $this->artisan('views:fix-tags', [
        '--path' => [$this->workspace],
    ])->assertExitCode(0);

    expect(File::get($file))->toBe('<input type="text" name="pet_name" required>');
});

it('reports changes in dry run mode without writing files', function (): void {
    $file = $this->workspace.'/dry-run.blade.php';
    $original = '<textarea name="body"rows="4"></textarea>';

    File::put($file, $original);

    $this->artisan('views:fix-tags', [
        '--path' => [$this->workspace],
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(File::get($file))->toBe($original);
});

it('removes dark utilities when the remove-dark option is set', function (): void {
    $file = $this->workspace.'/dark.blade.php';

    File::put($file, '<div class="text-sm dark:text-white md:dark:bg-black">Hi</div>');

    $this->artisan('views:fix-tags', [
        '--path' => [$this->workspace],
        '--remove-dark' => true,
    ])->assertExitCode(0);

    expect(File::get($file))->toBe('<div class="text-sm">Hi</div>');
});
