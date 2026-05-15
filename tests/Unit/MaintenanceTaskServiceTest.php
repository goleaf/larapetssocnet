<?php

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\Maintenance\BladeTagMaintenanceService;
use App\Services\Maintenance\MaintenanceTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('publishes due scheduled posts without an artisan command', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->subMinute(),
    ]);

    $result = app(MaintenanceTaskService::class)->publishScheduledPosts();

    expect($result->task)->toBe('publish-scheduled-posts')
        ->and($result->metrics['published'])->toBe(1)
        ->and($post->refresh()->status)->toBe(PostStatus::Published);
});

it('normalizes blade tags through the maintenance service', function (): void {
    $workspace = storage_path('framework/testing/maintenance-tags');
    $file = $workspace.'/sample.blade.php';

    File::ensureDirectoryExists($workspace);
    File::put($file, '<input type="text"name="pet_name"required>');

    try {
        $result = app(BladeTagMaintenanceService::class)->fix([$workspace]);

        expect($result->metrics['files_changed'])->toBe(1)
            ->and(File::get($file))->toBe('<input type="text" name="pet_name" required>');
    } finally {
        File::deleteDirectory($workspace);
    }
});
