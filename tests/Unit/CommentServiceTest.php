<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('allows replies to replies up to the configured visual depth', function (): void {
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

    $nestedReply = $service->create($post, $author, 'Nested reply', $reply);

    expect($nestedReply->parent_id)->toBe($reply->id);
});

it('flattens replies beyond the configured visual depth onto the third level parent', function (): void {
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

    $thirdLevel = Comment::query()->create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'parent_id' => $reply->id,
        'body' => 'Third level',
        'body_html' => 'Third level',
    ]);

    $service = app(CommentService::class);

    $flattenedReply = $service->create($post, $author, 'Still visually third level', $thirdLevel);

    expect($flattenedReply->parent_id)->toBe($reply->id);
});

it('rejects parents from another post', function (): void {
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
