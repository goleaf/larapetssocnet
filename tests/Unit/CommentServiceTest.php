<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('rejects replying to a reply', function () {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $parent = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'body' => 'Parent',
        'body_html' => 'Parent',
    ]);

    $reply = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'parent_id' => $parent->id,
        'body' => 'Reply',
        'body_html' => 'Reply',
    ]);

    $service = app(CommentService::class);

    $this->expectException(ValidationException::class);
    $service->create($post, $author, 'Too deep', $reply);
});

it('rejects parents from another post', function () {
    $author = User::factory()->create();
    $postA = Post::factory()->for($author)->create();
    $postB = Post::factory()->for($author)->create();

    $parent = Comment::query()->create([
        'post_id' => $postA->id,
        'user_id' => $author->id,
        'body' => 'Parent',
        'body_html' => 'Parent',
    ]);

    $service = app(CommentService::class);

    $this->expectException(ValidationException::class);
    $service->create($postB, $author, 'Invalid', $parent);
});
