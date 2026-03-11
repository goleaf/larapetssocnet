<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Config;

uses(Tests\TestCase::class);

it('renders anonymous Blade components with Blaze optimization enabled', function (): void {
    $blazeConfig = app(Config::class);

    expect($blazeConfig->shouldCompile(resource_path('views/components/primary-button.blade.php')))->toBeBool();

    $primaryButton = Blade::render('<x-primary-button>Save</x-primary-button>');
    $avatar = Blade::render('<x-ui.avatar name="Jane Doe" size="sm" />');

    expect($primaryButton)
        ->toContain('Save')
        ->toContain('btn-base')
        ->toContain('btn-primary');

    expect($avatar)
        ->toContain('JD')
        ->toContain('h-8 w-8');
});
