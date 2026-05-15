<?php

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks non admins from maintenance routes', function (): void {
    $user = User::factory()->create(['role' => 'member']);

    $this->actingAs($user)
        ->get(route('admin.maintenance.index'))
        ->assertForbidden();
});

it('runs maintenance tasks from the admin panel', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $post = Post::factory()->create([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.maintenance.run', 'publish-scheduled-posts'))
        ->assertRedirectToRoute('admin.maintenance.index')
        ->assertSessionHas('maintenance_result');

    expect($post->refresh()->status)->toBe(PostStatus::Published);
});
