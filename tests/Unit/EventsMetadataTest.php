<?php

use App\Events\MediaUploaded;
use App\Events\MessageSent;
use App\Events\PetCreated;
use App\Events\PostCreated;
use App\Events\PostDeleted;
use App\Events\PostLiked;
use App\Events\PostUnliked;
use App\Events\TagsProcessed;
use App\Events\UserBlocked;
use App\Events\UserFollowed;
use App\Events\UserUnblocked;
use App\Events\UserUnfollowed;
use App\Models\Content\Like;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Messaging\Message;
use App\Models\Pets\Pet;
use App\Models\Social\Follow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

it('describes user relationship events with stable payloads', function (): void {
    $occurredAt = CarbonImmutable::parse('2026-05-14 12:00:00 UTC');
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $follow = Follow::factory()->create([
        'follower_id' => $actor->getKey(),
        'following_id' => $target->getKey(),
    ]);

    $blocked = new UserBlocked($actor, $target, $occurredAt);
    $unblocked = new UserUnblocked($actor, $target, $occurredAt);
    $followed = new UserFollowed($follow, $actor, $target, $occurredAt);
    $unfollowed = new UserUnfollowed($actor, $target, true, $occurredAt);

    expect($blocked->eventName())->toBe('user.blocked')
        ->and($blocked->actorId())->toBe((int) $actor->getKey())
        ->and($blocked->subjectId())->toBe((int) $target->getKey())
        ->and($blocked->relatedUserIds())->toBe([(int) $actor->getKey(), (int) $target->getKey()])
        ->and($blocked->payload())->toHaveKey('occurred_at', $blocked->occurredAtIso())
        ->and($unblocked->eventName())->toBe('user.unblocked')
        ->and($followed->payload())->toMatchArray([
            'follow_id' => (int) $follow->getKey(),
            'follower_id' => (int) $actor->getKey(),
            'target_id' => (int) $target->getKey(),
        ])
        ->and($unfollowed->payload())->toHaveKey('was_following', true);
});

it('describes post engagement events with actor and subject ids', function (): void {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $moderator = User::factory()->create();
    $post = Post::factory()->for($owner)->create();
    $like = Like::factory()->create([
        'post_id' => $post->getKey(),
        'user_id' => $actor->getKey(),
    ]);

    $created = new PostCreated($post);
    $deleted = new PostDeleted($post, $moderator);
    $liked = new PostLiked($like, $actor, $post);
    $unliked = new PostUnliked($actor, $post, true);

    expect($created->eventName())->toBe('post.created')
        ->and($created->payload())->toMatchArray([
            'post_id' => (int) $post->getKey(),
            'user_id' => (int) $owner->getKey(),
            'visibility' => Post::VISIBILITY_PUBLIC,
        ])
        ->and($deleted->actorId())->toBe((int) $moderator->getKey())
        ->and($liked->relatedUserIds())->toBe([(int) $actor->getKey(), (int) $owner->getKey()])
        ->and($liked->payload())->toHaveKey('like_id', (int) $like->getKey())
        ->and($unliked->payload())->toHaveKey('was_liked', true);
});

it('describes message pet tag and media events', function (): void {
    $owner = User::factory()->create();
    $receiver = User::factory()->create();
    $message = Message::factory()->create([
        'sender_id' => $owner->getKey(),
        'receiver_id' => $receiver->getKey(),
    ]);
    $pet = Pet::factory()->for($owner, 'owner')->create();
    $media = new Media;
    $media->forceFill([
        'id' => 123,
        'model_type' => Post::class,
        'model_id' => 456,
        'collection_name' => 'photos',
        'mime_type' => 'image/jpeg',
        'size' => 2048,
    ]);

    $messageSent = new MessageSent($message, $owner, $receiver);
    $petCreated = new PetCreated($pet, $owner);
    $tagsProcessed = new TagsProcessed(Post::class, 99, ['cats', 'rescue']);
    $mediaUploaded = new MediaUploaded($media, 'photo', (int) $owner->getKey());

    expect($messageSent->eventName())->toBe('message.sent')
        ->and($messageSent->relatedUserIds())->toBe([(int) $owner->getKey(), (int) $receiver->getKey()])
        ->and($petCreated->payload())->toHaveKey('pet_id', (int) $pet->getKey())
        ->and($tagsProcessed->payload())->toMatchArray([
            'taggable_type' => Post::class,
            'taggable_id' => 99,
            'tags' => ['cats', 'rescue'],
            'tag_count' => 2,
        ])
        ->and($mediaUploaded->payload())->toMatchArray([
            'media_id' => 123,
            'type' => 'photo',
            'owner_id' => (int) $owner->getKey(),
            'collection' => 'photos',
            'size' => 2048,
        ]);
});
