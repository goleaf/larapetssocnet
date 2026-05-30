<?php

use Illuminate\Support\Facades\File;
use Livewire\Component;

it('keeps comment Livewire views free of inline PHP and Volt directives', function (): void {
    collect(File::files(resource_path('views/livewire/comments')))
        ->each(function (SplFileInfo $file): void {
            $contents = File::get($file->getPathname());

            expect($contents)
                ->not->toContain('@php')
                ->not->toContain('@volt')
                ->not->toContain('Livewire\\Volt');
        });
});

it('keeps comment Livewire components as class based components', function (): void {
    collect(File::files(app_path('Livewire/Comments')))
        ->each(function (SplFileInfo $file): void {
            $class = 'App\\Livewire\\Comments\\'.str($file->getBasename('.php'))->toString();

            expect(is_subclass_of($class, Component::class))->toBeTrue();
            expect(File::get($file->getPathname()))
                ->not->toContain('Livewire\\Volt')
                ->not->toContain('Volt\\');
        });
});
