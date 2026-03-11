<?php

namespace App\Providers;

use App\Enums\FollowAbility;
use App\Events\UserBlocked;
use App\Listeners\CancelPendingRequestsOnBlock;
use App\Listeners\RemoveFollowOnBlock;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Group;
use App\Models\MarketplaceListing;
use App\Models\Message;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use App\Models\UsernameRedirect;
use App\Notifications\QueueBusyAlert;
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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

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
        if (class_exists(Blaze::class)) {
            try {
                Blaze::optimize()->in(resource_path('views/components'));
            } catch (\Throwable) {
            }
        }

        if ($this->app->isLocal()) {
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
        $this->defineFollowGate(FollowAbility::Follow, [FollowPolicy::class, 'follow']);
        $this->defineFollowGate(FollowAbility::Unfollow, [FollowPolicy::class, 'unfollow']);
        $this->defineFollowGate(FollowAbility::ViewFollowers, [FollowPolicy::class, 'viewFollowers']);
        $this->defineFollowGate(FollowAbility::ViewFollowing, [FollowPolicy::class, 'viewFollowing']);
        Pet::observe(PetObserver::class);
        Message::observe(MessageObserver::class);
        Post::observe(PostObserver::class);
        EventFacade::listen(UserBlocked::class, RemoveFollowOnBlock::class);
        EventFacade::listen(UserBlocked::class, CancelPendingRequestsOnBlock::class);
        EventFacade::listen(function (QueueBusy $event): void {
            Log::warning('Queue busy threshold exceeded.', [
                'connection' => $event->connection,
                'queue' => $event->queue,
                'size' => $event->size,
            ]);

            $alertEmail = trim((string) config('queue.monitor.alert_email'));

            if ($alertEmail !== '') {
                Notification::route('mail', $alertEmail)
                    ->notify(new QueueBusyAlert($event->connection, $event->queue, $event->size));
            }
        });

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
