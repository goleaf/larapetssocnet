<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Database\Seeders\PostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds posts for existing users without creating extra users', function (): void {
    $user = User::factory()->create();

    Pet::factory()
        ->for($user)
        ->create([
            'species' => 'dog',
            'breed' => 'mixed',
        ]);

    $this->seed(PostSeeder::class);

    expect(Post::query()->count())->toBe(100);
    expect(User::query()->count())->toBe(1);
});
