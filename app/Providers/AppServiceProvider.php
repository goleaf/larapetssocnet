<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Group;
use App\Models\MarketplaceListing;
use App\Models\Message;
use App\Models\Pet;
use App\Models\Post;
use App\Policies\CommentPolicy;
use App\Policies\EventPolicy;
use App\Policies\GroupPolicy;
use App\Policies\MarketplaceListingPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PetPolicy;
use App\Policies\PostPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
