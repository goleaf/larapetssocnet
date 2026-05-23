<?php

namespace Tests\Feature;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_report_post(): void
    {
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create([
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($reporter)
            ->post(route('posts.report', $post), [
                'reason' => 'spam',
                'details' => 'Spam content',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'reportable_type' => (new Post)->getMorphClass(),
            'reportable_id' => $post->id,
            'reason' => 'spam',
        ]);
    }

    public function test_authenticated_user_can_report_comment(): void
    {
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create([
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);
        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'body' => 'Bad comment',
            'body_html' => 'Bad comment',
        ]);

        $this->actingAs($reporter)
            ->post(route('comments.report', [$post, $comment]), [
                'reason' => 'abuse',
                'details' => 'Abusive comment',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'reportable_type' => (new Comment)->getMorphClass(),
            'reportable_id' => $comment->id,
            'reason' => 'abuse',
        ]);
    }

    public function test_authenticated_user_can_report_user(): void
    {
        $reporter = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($reporter)
            ->post(route('users.report', $target), [
                'reason' => Report::PROFILE_REASON_FAKE_OR_MISLEADING,
                'details' => 'Inappropriate behavior',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'reportable_type' => (new User)->getMorphClass(),
            'reportable_id' => $target->id,
            'reason' => Report::PROFILE_REASON_FAKE_OR_MISLEADING,
        ]);
    }

    public function test_profile_report_reasons_are_not_valid_for_post_reports(): void
    {
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create([
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($reporter)
            ->post(route('posts.report', $post), [
                'reason' => Report::PROFILE_REASON_SPAM_ACCOUNT,
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, Report::query()->count());
    }

    public function test_user_cannot_report_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create([
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($user)
            ->post(route('posts.report', $post), [
                'reason' => 'spam',
            ])
            ->assertForbidden();

        $this->assertSame(0, Report::query()->count());
    }

    public function test_user_cannot_report_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.show', ['user' => $user]))
            ->post(route('users.report', $user), [
                'reason' => Report::PROFILE_REASON_FAKE_OR_MISLEADING,
            ])
            ->assertForbidden();

        $this->assertSame(0, Report::query()->count());
    }
}
