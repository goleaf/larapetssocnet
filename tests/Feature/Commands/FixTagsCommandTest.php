<?php

use App\Services\Maintenance\BladeTagMaintenanceService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

    app(BladeTagMaintenanceService::class)->fix([$this->workspace]);

    expect(File::get($file))->toBe('<input type="text" name="pet_name" required>');
});

it('reports changes in dry run mode without writing files', function (): void {
    $file = $this->workspace.'/dry-run.blade.php';
    $original = '<textarea name="body"rows="4"></textarea>';

    File::put($file, $original);

    $result = app(BladeTagMaintenanceService::class)->fix([$this->workspace], dryRun: true);

    expect($result->metrics['files_changed'])->toBe(1)
        ->and(File::get($file))->toBe($original);
});

it('removes dark utilities when the remove-dark option is set', function (): void {
    $file = $this->workspace.'/dark.blade.php';

    File::put($file, '<div class="text-sm dark:text-white md:dark:bg-black">Hi</div>');

    app(BladeTagMaintenanceService::class)->fix([$this->workspace], removeDark: true);

    expect(File::get($file))->toBe('<div class="text-sm">Hi</div>');
});

it('preserves blade attribute spacing when removing dark utilities', function (): void {
    $file = $this->workspace.'/component-attrs.blade.php';

    File::put(
        $file,
        '<x-ui.button class="px-2 dark:bg-black" :href="route(\'login\')" variant="primary" size="sm">Log In</x-ui.button>'
    );

    app(BladeTagMaintenanceService::class)->fix([$this->workspace], removeDark: true);

    expect(File::get($file))
        ->toBe('<x-ui.button class="px-2" :href="route(\'login\')" variant="primary" size="sm">Log In</x-ui.button>');
});

it('inserts space before a blade attribute bag after a quoted attribute value', function (): void {
    $file = $this->workspace.'/attribute-bag.blade.php';

    File::put($file, '<x-ui.input class="w-full"{{ $attributes }} />');

    app(BladeTagMaintenanceService::class)->fix([$this->workspace]);

    expect(File::get($file))->toBe('<x-ui.input class="w-full" {{ $attributes }} />');
});

it('inserts space after single-quoted values before boolean attributes', function (): void {
    $file = $this->workspace.'/single-quotes.blade.php';

    File::put($file, "<x-ui.button type='button'disabled>Save</x-ui.button>");

    app(BladeTagMaintenanceService::class)->fix([$this->workspace]);

    expect(File::get($file))->toBe("<x-ui.button type='button' disabled>Save</x-ui.button>");
});

it('inserts space when an attribute name follows a bracket-terminated expression', function (): void {
    $file = $this->workspace.'/bracket-expression.blade.php';

    File::put($file, '<x-ui.tabs :tabs="$tabs[$active]"icon="paw" />');

    app(BladeTagMaintenanceService::class)->fix([$this->workspace]);

    expect(File::get($file))->toBe('<x-ui.tabs :tabs="$tabs[$active]" icon="paw" />');
});
