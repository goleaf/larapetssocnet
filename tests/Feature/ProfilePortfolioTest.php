<?php

use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use App\Models\Identity\ProfilePortfolioPost;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a public profile portfolio with selected posts in curated order', function (): void {
    $owner = User::factory()->create([
        'name' => 'Mara Finch',
        'username' => 'mara_portfolio',
        'display_name' => 'Mara and Basil',
        'is_private' => false,
    ]);

    $third = portfolioPost($owner, 'Third accent story', reactions: 2);
    $first = portfolioPost($owner, 'First feature story', reactions: 15);
    $second = portfolioPost($owner, 'Second secondary story', reactions: 8);

    ProfilePortfolioPost::factory()->create(['user_id' => $owner->id, 'post_id' => $first->id, 'display_order' => 1]);
    ProfilePortfolioPost::factory()->create(['user_id' => $owner->id, 'post_id' => $second->id, 'display_order' => 2]);
    ProfilePortfolioPost::factory()->create(['user_id' => $owner->id, 'post_id' => $third->id, 'display_order' => 3]);

    $this->get(route('profile.portfolio', ['user' => $owner->username]))
        ->assertOk()
        ->assertSee('data-ui="profile-portfolio-page"', false)
        ->assertSee('Mara and Basil')
        ->assertSeeInOrder([
            'First feature story',
            'Second secondary story',
            'Third accent story',
        ]);
});

it('stores selected public posts from profile settings in requested portfolio order', function (): void {
    $owner = User::factory()->create(['username' => 'portfolio_owner']);
    $first = portfolioPost($owner, 'First saved portfolio story');
    $second = portfolioPost($owner, 'Second saved portfolio story');

    $this->actingAs($owner)
        ->from(route('settings.profile'))
        ->put(route('settings.profile.portfolio.update'), [
            'portfolio_posts' => [$first->id, $second->id],
            'portfolio_positions' => [
                $first->id => 2,
                $second->id => 1,
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    expect(ProfilePortfolioPost::query()
        ->where('user_id', $owner->id)
        ->orderBy('display_order')
        ->pluck('post_id')
        ->all())->toBe([$second->id, $first->id]);
});

it('rejects more than twelve selected portfolio posts', function (): void {
    $owner = User::factory()->create(['username' => 'portfolio_limit']);
    $posts = Post::factory()
        ->count(13)
        ->for($owner, 'author')
        ->create(['visibility' => Post::VISIBILITY_PUBLIC]);

    $this->actingAs($owner)
        ->from(route('settings.profile'))
        ->put(route('settings.profile.portfolio.update'), [
            'portfolio_posts' => $posts->pluck('id')->all(),
        ])
        ->assertSessionHasErrors('portfolio_posts')
        ->assertRedirect(route('settings.profile'));

    expect(ProfilePortfolioPost::query()->where('user_id', $owner->id)->count())->toBe(0);
});

it('rejects posts that are not publicly visible to guests', function (): void {
    $owner = User::factory()->create(['username' => 'portfolio_private']);
    $privatePost = portfolioPost($owner, 'Private story should not be selected', visibility: Post::VISIBILITY_PRIVATE);

    $this->actingAs($owner)
        ->from(route('settings.profile'))
        ->put(route('settings.profile.portfolio.update'), [
            'portfolio_posts' => [$privatePost->id],
        ])
        ->assertSessionHasErrors('portfolio_posts')
        ->assertRedirect(route('settings.profile'));

    $this->assertDatabaseMissing('profile_portfolio_posts', [
        'user_id' => $owner->id,
        'post_id' => $privatePost->id,
    ]);
});

it('shows the portfolio management interface on profile settings', function (): void {
    $owner = User::factory()->create(['username' => 'portfolio_settings']);
    portfolioPost($owner, 'Eligible public post for the portfolio manager');

    $this->actingAs($owner)
        ->get(route('settings.profile'))
        ->assertOk()
        ->assertSee('data-ui="settings-profile-portfolio"', false)
        ->assertSee(route('profile.portfolio', ['user' => $owner->username]))
        ->assertSee('Eligible public post for the portfolio manager');
});

function portfolioPost(User $owner, string $body, int $reactions = 0, string $visibility = Post::VISIBILITY_PUBLIC): Post
{
    $post = Post::factory()
        ->for($owner, 'author')
        ->create([
            'body' => $body,
            'body_html' => '<p>'.e($body).'</p>',
            'visibility' => $visibility,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'reactions_count' => $reactions,
        ]);

    PostMedia::factory()->for($post)->create([
        'media_type' => 'image',
        'file_path' => 'posts/'.$post->id.'.jpg',
        'order' => 0,
    ]);

    return $post;
}
