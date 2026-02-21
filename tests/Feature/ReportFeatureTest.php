<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
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
            'reportable_type' => Post::class,
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
        ]);

        $this->actingAs($reporter)
            ->post(route('comments.report', [$post, $comment]), [
                'reason' => 'abuse',
                'details' => 'Abusive comment',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'reportable_type' => Comment::class,
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
                'reason' => 'other',
                'details' => 'Inappropriate behavior',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'reportable_type' => User::class,
            'reportable_id' => $target->id,
            'reason' => 'other',
        ]);
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
                'reason' => 'other',
            ])
            ->assertRedirect(route('profile.show', ['user' => $user]))
            ->assertSessionHasErrors('report');

        $this->assertSame(0, Report::query()->count());
    }
}

