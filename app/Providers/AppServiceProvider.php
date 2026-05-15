<?php

namespace App\Providers;

use App\Enums\FollowAbility;
use App\Events\UserBlocked;
use App\Listeners\CancelPendingRequestsOnBlock;
use App\Listeners\RemoveFollowOnBlock;
use App\Models\Activities\Event;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Messaging\Message;
use App\Models\Pets\Pet;
use App\Notifications\QueueBusyAlert;
use App\Observers\CommentObserver;
use App\Observers\MessageObserver;
use App\Observers\PetObserver;
use App\Observers\PostObserver;
use App\Policies\CommentPolicy;
use App\Policies\EventPolicy;
use App\Policies\FollowPolicy;
use App\Policies\GroupPolicy;
use App\Policies\ListingPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PetPolicy;
use App\Policies\PostPolicy;
use App\Policies\UserPolicy;
use App\Services\UsernameRedirectResolver;
use App\Support\Models\LegacyModelMorphMap;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
        LegacyModelMorphMap::register();

        if ($this->app->environment('local')) {
            DB::listen(function (QueryExecuted $query): void {
                if ($query->time > 100) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->toRawSql(),
                        'time_ms' => $query->time,
                    ]);
                }
            });
        }

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Pet::class, PetPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(MarketplaceListing::class, ListingPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        foreach (FollowAbility::cases() as $ability) {
            $this->defineFollowGate($ability, [FollowPolicy::class, $ability->policyMethod()]);
        }

        Pet::observe(PetObserver::class);
        Message::observe(MessageObserver::class);
        Post::observe(PostObserver::class);
        Comment::observe(CommentObserver::class);
        EventFacade::listen(UserBlocked::class, RemoveFollowOnBlock::class);
        EventFacade::listen(UserBlocked::class, CancelPendingRequestsOnBlock::class);
        EventFacade::listen(function (QueueBusy $event): void {
            Log::warning('Queue busy threshold exceeded.', [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'size' => $event->size,
            ]);

            $alertEmail = trim((string) config('queue.monitor.alert_email'));

            if ($alertEmail !== '') {
                Notification::route('mail', $alertEmail)
                    ->notify(new QueueBusyAlert($event->connectionName, $event->queue, $event->size));
            }
        });

        Route::bind('user', function (string $value): User {
            request()->attributes->set('username_raw', $value);
            request()->attributes->set('username_normalized', UsernameNormalizer::normalize($value));

            $resolution = app(UsernameRedirectResolver::class)->resolve($value);

            if (! $resolution) {
                abort(404, 'User not found.');
            }

            if ($resolution['redirect']) {
                request()->attributes->set('username_redirect', $resolution['redirect']);
            }

            return $resolution['user'];
        });
    }

    /**
     * @param  array{0: class-string, 1: string}  $callback
     */
    private function defineFollowGate(FollowAbility $ability, array $callback): void
    {
        if (! Gate::has($ability)) {
            Gate::define($ability, $callback);
        }
    }
}
