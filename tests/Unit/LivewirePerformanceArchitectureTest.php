<?php

function livewirePerformanceSource(string $relativePath): string
{
    $contents = file_get_contents(base_path($relativePath));

    expect($contents)->toBeString();

    return (string) $contents;
}

it('bundles independent lazy feed sidebars behind placeholders', function (): void {
    $source = livewirePerformanceSource('resources/views/livewire/pages/feed/index.blade.php');

    expect($source)
        ->toContain('<livewire:feed.left-sidebar lazy.bundle />')
        ->toContain('<livewire:feed.right-sidebar lazy.bundle />');

    expect(livewirePerformanceSource('resources/views/components/feed/⚡left-sidebar.blade.php'))
        ->toContain('data-ui="feed-left-sidebar-skeleton"');

    expect(livewirePerformanceSource('resources/views/components/feed/⚡right-sidebar.blade.php'))
        ->toContain('data-ui="feed-right-sidebar-skeleton"');
});

it('uses async feed polling with data-loading controls', function (): void {
    $source = livewirePerformanceSource('resources/views/components/feed/⚡stream.blade.php');

    expect($source)
        ->toContain('use Livewire\Attributes\Async;')
        ->toContain('#[Async]')
        ->toContain('public function checkForNewPosts(): void')
        ->toContain('data-loading:pointer-events-none data-loading:opacity-60');
});

it('keeps draft and copy tracking actions renderless', function (): void {
    expect(livewirePerformanceSource('resources/views/components/posts/⚡share-menu.blade.php'))
        ->toContain('use Livewire\Attributes\Renderless;')
        ->toContain("#[Renderless]\n    public function trackCopyLink");

    expect(livewirePerformanceSource('resources/views/components/posts/⚡composer.blade.php'))
        ->toContain('use Livewire\Attributes\Renderless;')
        ->toContain("#[Renderless]\n    public function autosaveDraft");

    expect(livewirePerformanceSource('resources/views/components/posts/⚡comments-thread.blade.php'))
        ->toContain('use Livewire\Attributes\Renderless;')
        ->toContain("#[Renderless]\n    public function autosaveDraft");

    expect(livewirePerformanceSource('app/Livewire/Comments/TopLevelCommentComposer.php'))
        ->toContain('use Livewire\Attributes\Renderless;')
        ->toContain("#[Renderless]\n    public function autosaveDraft");
});
