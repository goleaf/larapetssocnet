<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Group;
use App\Models\MarketplaceListing;
use App\Models\Message;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use App\Models\UsernameRedirect;
use App\Observers\PostObserver;
use App\Events\UserBlocked;
use App\Listeners\CancelPendingRequestsOnBlock;
use App\Listeners\RemoveFollowOnBlock;
use App\Policies\CommentPolicy;
use App\Policies\EventPolicy;
use App\Policies\GroupPolicy;
use App\Policies\MarketplaceListingPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PetPolicy;
use App\Policies\PostPolicy;
use App\Policies\FollowPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Pet::class, PetPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(MarketplaceListing::class, MarketplaceListingPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::define('follow', [FollowPolicy::class, 'follow']);
        Gate::define('unfollow', [FollowPolicy::class, 'unfollow']);
        Gate::define('viewFollowers', [FollowPolicy::class, 'viewFollowers']);
        Gate::define('viewFollowing', [FollowPolicy::class, 'viewFollowing']);
        Post::observe(PostObserver::class);
        EventFacade::listen(UserBlocked::class, RemoveFollowOnBlock::class);
        EventFacade::listen(UserBlocked::class, CancelPendingRequestsOnBlock::class);

        Route::bind('user', function (string $value): User {
            $normalized = strtolower($value);
            request()->attributes->set('username_raw', $value);

            $user = User::query()->where('username', $normalized)->first();
            if ($user) {
                return $user;
            }

            $redirect = UsernameRedirect::query()
                ->active()
                ->where('old_username', $normalized)
                ->with('user')
                ->first();

            if ($redirect?->user) {
                request()->attributes->set('username_redirect', $redirect);

                return $redirect->user;
            }

            abort(404, 'User not found.');
        });
    }
}
