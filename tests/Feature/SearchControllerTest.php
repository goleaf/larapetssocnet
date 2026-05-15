<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest post search returns only public visible posts', function (): void {
    $author = User::factory()->create([
        'username' => 'search_author',
        'is_private' => false,
        'is_banned' => false,
    ]);

    Post::factory()->for($author)->create([
        'body' => 'needle public post',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'status' => 'published',
    ]);

    Post::factory()->for($author)->create([
        'body' => 'needle private post',
        'visibility' => Post::VISIBILITY_PRIVATE,
        'status' => 'published',
    ]);

    $this->get(route('search.index', ['type' => 'posts', 'q' => 'needle']))
        ->assertOk()
        ->assertSee('needle public post')
        ->assertDontSee('needle private post');
});

test('user search only returns discoverable users', function (): void {
    User::factory()->create([
        'name' => 'Findable Public User',
        'username' => 'findable_public',
        'is_private' => false,
        'is_banned' => false,
    ]);

    User::factory()->create([
        'name' => 'Findable Private User',
        'username' => 'findable_private',
        'is_private' => true,
        'is_banned' => false,
    ]);

    User::factory()->create([
        'name' => 'Findable Banned User',
        'username' => 'findable_banned',
        'is_private' => false,
        'is_banned' => true,
    ]);

    $this->get(route('search.index', ['type' => 'users', 'q' => 'Findable']))
        ->assertOk()
        ->assertSee('Findable Public User')
        ->assertDontSee('Findable Private User')
        ->assertDontSee('Findable Banned User');
});
