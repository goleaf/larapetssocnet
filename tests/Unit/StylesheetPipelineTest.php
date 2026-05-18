<?php

it('uses scss as the only application stylesheet source', function (): void {
    expect(is_dir(stylesheetProjectPath('resources/scss')))->toBeTrue()
        ->and(is_dir(stylesheetProjectPath('resources/css')))->toBeFalse()
        ->and(file_exists(stylesheetProjectPath('resources/scss/app.scss')))->toBeTrue();

    $partials = [
        'resources/scss/abstracts/_index.scss',
        'resources/scss/abstracts/_mixins.scss',
        'resources/scss/abstracts/_tokens.scss',
        'resources/scss/base/_base.scss',
        'resources/scss/base/_root.scss',
        'resources/scss/components/_actions.scss',
        'resources/scss/components/_forms.scss',
        'resources/scss/components/_navigation.scss',
        'resources/scss/components/_surfaces.scss',
        'resources/scss/components/_utilities.scss',
        'resources/scss/vendors/_mary.scss',
    ];

    foreach ($partials as $path) {
        expect(file_exists(stylesheetProjectPath($path)))->toBeTrue();
    }
});

it('points vite blade and tailwind to the scss entrypoint', function (): void {
    $entrypoint = 'resources/scss/app.scss';
    $vite = stylesheetFile('vite.config.js');
    $tailwind = stylesheetFile('tailwind.config.js');

    expect($vite)->toContain($entrypoint)
        ->and($vite)->toContain("loadPaths: ['node_modules']")
        ->and($vite)->toContain("outDir: 'build'")
        ->and($vite)->toContain("hotFile: 'hot'")
        ->and($vite)->not->toContain('resources/css/app.css')
        ->and($vite)->not->toContain("outDir: 'public/build'")
        ->and($tailwind)->toContain('./resources/**/*.scss');

    foreach (stylesheetBladeFiles() as $contents) {
        expect($contents)->not->toContain('resources/css/app.css');
    }
});

it('defines the scss compiler and lint toolchain', function (): void {
    $package = stylesheetJson('package.json');
    $composer = stylesheetJson('composer.json');
    $stylelint = stylesheetFile('stylelint.config.js');

    expect($package['devDependencies'] ?? [])->toHaveKeys([
        '@tailwindcss/postcss',
        'postcss-scss',
        'sass',
        'sass-embedded',
        'stylelint',
        'stylelint-config-standard-scss',
        'stylelint-order',
    ]);

    expect($package['scripts'] ?? [])->toMatchArray([
        'lint:scss' => 'stylelint "resources/scss/**/*.scss"',
        'lint:scss:fix' => 'stylelint "resources/scss/**/*.scss" --fix',
    ]);

    expect($composer['scripts'] ?? [])->toHaveKeys(['style:scss', 'style:scss:fix'])
        ->and($composer['scripts']['quality'] ?? [])->toContain('@style:scss')
        ->and(file_exists(stylesheetProjectPath('postcss.config.js')))->toBeTrue()
        ->and(stylesheetFile('postcss.config.js'))->toContain("'@tailwindcss/postcss'")
        ->and($stylelint)->toContain("customSyntax: 'postcss-scss'")
        ->and($stylelint)->toContain("'stylelint-config-standard-scss'")
        ->and($stylelint)->toContain("'stylelint-order'");
});

it('keeps shared design primitives as the source for repeated app blocks', function (): void {
    $tokens = stylesheetFile('resources/scss/abstracts/_tokens.scss');
    $surfaces = stylesheetFile('resources/scss/components/_surfaces.scss');
    $actions = stylesheetFile('resources/scss/components/_actions.scss');
    $tailwind = stylesheetFile('tailwind.config.js');

    expect($tokens)
        ->toContain("font-display: \"'GT Sectra'")
        ->toContain("font-body: \"'Söhne'")
        ->toContain('$open-design-warm-editorial')
        ->toContain('od-bg: #fbf6ee')
        ->toContain('od-primary: #c0512f')
        ->toContain('od-secondary: #2f5b4f')
        ->not->toContain('Playfair Display')
        ->not->toContain('DM Sans')
        ->and($tailwind)
        ->toContain('"GT Sectra"')
        ->toContain('"Söhne"')
        ->and($surfaces)
        ->toContain('.ui-card-interactive')
        ->toContain('.ui-list-item')
        ->toContain('.ui-token')
        ->toContain('.ui-media-frame')
        ->toContain('.ui-container')
        ->toContain('.ui-section')
        ->and($actions)
        ->toContain('.btn-default')
        ->toContain('.btn-outline')
        ->toContain('var(--surface-muted)')
        ->toContain('var(--text-muted)');
});

it('keeps shared select controls to one custom dropdown arrow', function (): void {
    $forms = stylesheetFile('resources/scss/components/_forms.scss');
    $select = stylesheetFile('resources/views/components/ui/select.blade.php');

    expect($forms)
        ->toContain('.form-select.form-select')
        ->toContain('background-image: none')
        ->and($select)
        ->toContain('form-select')
        ->toContain('appearance-none')
        ->toContain('pointer-events-none');
});

it('uses the warm editorial system without competing app theme toggles', function (): void {
    $appLayout = stylesheetFile('resources/views/layouts/app.blade.php');
    $guestLayout = stylesheetFile('resources/views/layouts/guest.blade.php');
    $navbar = stylesheetFile('resources/views/components/ui/navbar.blade.php');
    $javascript = stylesheetFile('resources/js/app.js');
    $settingsProfile = stylesheetFile('resources/views/settings/profile.blade.php');
    $debugbar = stylesheetFile('config/debugbar.php');

    expect($appLayout)
        ->not->toContain('fonts.bunny.net')
        ->not->toContain('outfit:')
        ->not->toContain('nunito-sans:')
        ->not->toContain('data-theme')
        ->and($guestLayout)
        ->not->toContain('data-theme')
        ->not->toContain('larapets-theme')
        ->not->toContain('prefers-color-scheme')
        ->not->toContain('themeController')
        ->and($navbar)
        ->not->toContain('toggleTheme')
        ->not->toContain('isDark')
        ->and($javascript)
        ->not->toContain("Alpine.store('ui'")
        ->not->toContain('get theme')
        ->not->toContain('THEME_STORAGE_KEY')
        ->not->toContain('matchMedia')
        ->not->toContain('themeController')
        ->and($settingsProfile)
        ->not->toContain('profile_theme')
        ->not->toContain('Profile theme')
        ->and($debugbar)
        ->toContain("'theme' => env('DEBUGBAR_THEME', 'light')")
        ->not->toContain("'theme' => env('DEBUGBAR_THEME', 'auto')");
});

/**
 * @return array<string, mixed>
 */
function stylesheetJson(string $path): array
{
    return json_decode(stylesheetFile($path), true, 512, JSON_THROW_ON_ERROR);
}

function stylesheetFile(string $path): string
{
    return (string) file_get_contents(stylesheetProjectPath($path));
}

function stylesheetProjectPath(string $path = ''): string
{
    $root = dirname(__DIR__, 2);

    return $path === '' ? $root : $root.'/'.$path;
}

/**
 * @return array<string, string>
 */
function stylesheetBladeFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(stylesheetProjectPath('resources/views'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relativePath = str_replace(stylesheetProjectPath().'/', '', $file->getPathname());
        $files[$relativePath] = (string) file_get_contents($file->getPathname());
    }

    return $files;
}
