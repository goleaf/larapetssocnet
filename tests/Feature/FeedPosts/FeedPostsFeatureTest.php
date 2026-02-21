<?php

namespace Tests\Feature\FeedPosts;

use App\Models\Comment;
use App\Models\Post;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedPostsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_shows_only_own_and_following_posts_with_visibility_and_block_rules(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create();
        $unfollowed = User::factory()->create();
        $blocked = User::factory()->create();

        $viewer->following()->attach($followed->id);
        $viewer->blockedUsers()->attach($blocked->id);

        $ownPrivatePost = Post::query()->create([
            'user_id' => $viewer->id,
            'body' => 'own-private-post',
            'visibility' => Post::VISIBILITY_PRIVATE,
        ]);

        $followedFollowersPost = Post::query()->create([
            'user_id' => $followed->id,
            'body' => 'followed-followers-post',
            'visibility' => Post::VISIBILITY_FOLLOWERS,
        ]);

        $followedPrivatePost = Post::query()->create([
            'user_id' => $followed->id,
            'body' => 'followed-private-post',
            'visibility' => Post::VISIBILITY_PRIVATE,
        ]);

        $unfollowedPublicPost = Post::query()->create([
            'user_id' => $unfollowed->id,
            'body' => 'unfollowed-public-post',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $blockedPublicPost = Post::query()->create([
            'user_id' => $blocked->id,
            'body' => 'blocked-public-post',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $response = $this->actingAs($viewer)->get(route('feed.index'));

        $response->assertOk();
        $response->assertSee($ownPrivatePost->body);
        $response->assertSee($followedFollowersPost->body);
        $response->assertDontSee($followedPrivatePost->body);
        $response->assertDontSee($unfollowedPublicPost->body);
        $response->assertDontSee($blockedPublicPost->body);
    }

    public function test_reaction_endpoint_toggles_and_updates_likes_count(): void
    {
        $user = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $user->id,
            'body' => 'reactable-post',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $firstReaction = $this->actingAs($user)
            ->postJson(route('posts.react', $post), ['type' => 'love']);

        $firstReaction
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.likes_count', 1)
            ->assertJsonPath('data.current_reaction', 'love');

        $this->assertDatabaseHas('post_reactions', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'type' => 'love',
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'likes_count' => 1,
        ]);

        $toggleOff = $this->actingAs($user)
            ->postJson(route('posts.react', $post), ['type' => 'love']);

        $toggleOff
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.likes_count', 0)
            ->assertJsonPath('data.current_reaction', null);

        $this->assertDatabaseMissing('post_reactions', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $replace = $this->actingAs($user)
            ->postJson(route('posts.react', $post), ['type' => 'wow']);

        $replace
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.likes_count', 1)
            ->assertJsonPath('data.current_reaction', 'wow');
    }

    public function test_reaction_endpoint_accepts_all_supported_reaction_types(): void
    {
        $user = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $user->id,
            'body' => 'reactable-post-supported-types',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        foreach (['love', 'cute', 'funny', 'wow', 'sad', 'support'] as $type) {
            $response = $this->actingAs($user)
                ->postJson(route('posts.react', $post), ['type' => $type]);

            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.current_reaction', $type);
        }
    }

    public function test_reaction_endpoint_rejects_invalid_reaction_type(): void
    {
        $user = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $user->id,
            'body' => 'reactable-post-invalid-type',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($user)
            ->postJson(route('posts.react', $post), ['type' => 'angry'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_comments_support_one_level_replies_and_refresh_comments_count(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();

        $post = Post::query()->create([
            'user_id' => $author->id,
            'body' => 'post-with-comments',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($author)->post(route('posts.comments.store', $post), [
            'body' => 'top-level-comment',
        ])->assertRedirect();

        $topLevelComment = Comment::query()->where('post_id', $post->id)->whereNull('parent_id')->firstOrFail();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'comments_count' => 1,
        ]);

        $this->actingAs($replier)->post(route('posts.comments.store', $post), [
            'body' => 'first-level-reply',
            'parent_id' => $topLevelComment->id,
        ])->assertRedirect();

        $replyComment = Comment::query()->where('post_id', $post->id)->where('parent_id', $topLevelComment->id)->firstOrFail();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'comments_count' => 2,
        ]);

        $this->actingAs($author)
            ->from(route('posts.show', $post))
            ->post(route('posts.comments.store', $post), [
                'body' => 'invalid-depth-two-reply',
                'parent_id' => $replyComment->id,
            ])
            ->assertRedirect(route('posts.show', $post))
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'comments_count' => 2,
        ]);

        $this->actingAs($author)
            ->delete(route('posts.comments.destroy', [$post, $topLevelComment]))
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'comments_count' => 0,
        ]);
    }

    public function test_comment_reaction_endpoint_toggles_and_updates_reactions_count(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $author->id,
            'body' => 'post-for-comment-reaction',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'comment-to-react',
        ]);

        $this->actingAs($reactor)
            ->postJson(route('posts.comments.react', [$post, $comment]), ['type' => 'support'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_reaction', 'support')
            ->assertJsonPath('data.reactions_count', 1);

        $this->assertDatabaseHas('reactions', [
            'reactable_type' => Comment::class,
            'reactable_id' => $comment->id,
            'user_id' => $reactor->id,
            'type' => 'support',
        ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'reactions_count' => 1,
        ]);

        $this->actingAs($reactor)
            ->postJson(route('posts.comments.react', [$post, $comment]), ['type' => 'support'])
            ->assertOk()
            ->assertJsonPath('data.current_reaction', null)
            ->assertJsonPath('data.reactions_count', 0);
    }

    public function test_comment_reaction_endpoint_rejects_invalid_reaction_type(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $author->id,
            'body' => 'post-for-comment-reaction-invalid',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'comment-to-react-invalid',
        ]);

        $this->actingAs($reactor)
            ->postJson(route('posts.comments.react', [$post, $comment]), ['type' => 'angry'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_save_toggle_saves_and_unsaves_post(): void
    {
        $user = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->id,
            'body' => 'savable-post',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($user)
            ->post(route('posts.save.toggle', $post))
            ->assertRedirect();

        $this->assertDatabaseHas('saved_posts', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(1, SavedPost::query()->where('post_id', $post->id)->where('user_id', $user->id)->count());

        $this->actingAs($user)
            ->post(route('posts.save.toggle', $post))
            ->assertRedirect();

        $this->assertDatabaseMissing('saved_posts', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_saved_posts_page_shows_only_user_saved_posts(): void
    {
        $viewer = User::factory()->create();
        $otherUser = User::factory()->create();

        $savedPost = Post::query()->create([
            'user_id' => $otherUser->id,
            'body' => 'saved-post-visible',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $notSavedPost = Post::query()->create([
            'user_id' => $otherUser->id,
            'body' => 'not-saved-post-hidden',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        SavedPost::query()->create([
            'post_id' => $savedPost->id,
            'user_id' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('saved.index'));

        $response->assertOk();
        $response->assertSee('saved-post-visible');
        $response->assertDontSee('not-saved-post-hidden');
    }

    public function test_saved_posts_page_hides_saved_posts_with_private_visibility(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        $privatePost = Post::query()->create([
            'user_id' => $author->id,
            'body' => 'saved-but-now-private',
            'visibility' => Post::VISIBILITY_PRIVATE,
        ]);

        SavedPost::query()->create([
            'post_id' => $privatePost->id,
            'user_id' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('saved.index'));

        $response->assertOk();
        $response->assertDontSee('saved-but-now-private');
    }
}
