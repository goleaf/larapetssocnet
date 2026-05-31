<?php

namespace App\Providers;

use App\Enums\FollowAbility;
use App\Events\UserBlocked;
use App\Listeners\CancelPendingRequestsOnBlock;
use App\Listeners\RemoveFollowOnBlock;
use App\Mail\Transport\PhpMailTransport;
use App\Models\Activities\Event;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Messaging\Message;
use App\Models\Pets\Pet;
use App\Notifications\Mail\Operations\QueueBusyAlert;
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
use App\Services\ActiveStatusService;
use App\Services\Performance\DatabaseQueryPerformanceService;
use App\Services\UsernameRedirectResolver;
use App\Support\Auth\PasswordPolicy;
use App\Support\Models\LegacyModelMorphMap;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            DatabaseQueryPerformanceService::class,
            fn (): DatabaseQueryPerformanceService => new DatabaseQueryPerformanceService,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('phpmail', fn (): PhpMailTransport => new PhpMailTransport);

        Password::defaults(fn (): Password => PasswordPolicy::rule());

        LegacyModelMorphMap::register();

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            $route = request()->route();
            $componentNames = collect((array) request()->input('components', []))
                ->map(fn (mixed $component): ?string => is_array($component) ? data_get($component, 'name') : null)
                ->filter()
                ->values()
                ->all();

            Log::warning('Eloquent lazy-loading violation detected.', [
                'model' => $model::class,
                'relation' => $relation,
                'route' => $route?->getName(),
                'route_action' => $route?->getActionName(),
                'livewire_components' => $componentNames,
                'url' => request()->fullUrl(),
                'trace' => $this->app->environment(['local', 'testing'])
                    ? collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15))
                        ->map(fn (array $frame): array => [
                            'file' => $frame['file'] ?? null,
                            'line' => $frame['line'] ?? null,
                            'function' => $frame['function'],
                            'class' => $frame['class'] ?? null,
                        ])
                        ->all()
                    : null,
            ]);

            if (! $this->app->isProduction()) {
                throw new LazyLoadingViolationException($model, $relation);
            }
        });

        Livewire::listen('mount', function (): void {
            $user = auth()->user();

            if ($user instanceof User) {
                app(ActiveStatusService::class)->touch($user);
            }
        });

        if (
            $this->app->environment(['local', 'testing'])
            && (bool) config('database-performance.enabled', true)
        ) {
            $this->configureDatabasePerformanceMonitoring();
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

        RateLimiter::for('social-follows', function (Request $request): array {
            $key = (string) ($request->user()?->getKey() ?? $request->ip());

            return [
                Limit::perHour(50)
                    ->by('hour:'.$key)
                    ->response(fn (Request $request, array $headers): Response => $this->socialFollowLimitResponse($request, $headers)),
                Limit::perDay(200)
                    ->by('day:'.$key)
                    ->response(fn (Request $request, array $headers): Response => $this->socialFollowLimitResponse($request, $headers)),
            ];
        });

        RateLimiter::for('expensive-search', function (Request $request): array {
            $key = (string) ($request->user()?->getKey() ?? $request->ip());

            return [
                Limit::perMinute(40)->by('minute:'.$key),
                Limit::perHour(300)->by('hour:'.$key),
            ];
        });

        RateLimiter::for('catalog-browse', function (Request $request): array {
            $key = (string) ($request->user()?->getKey() ?? $request->ip());

            return [
                Limit::perMinute(90)->by('minute:'.$key),
                Limit::perHour(600)->by('hour:'.$key),
            ];
        });

        RateLimiter::for('polling-refresh', function (Request $request): array {
            $key = (string) ($request->user()?->getKey() ?? $request->ip());

            return [
                Limit::perMinute(120)->by('minute:'.$key),
            ];
        });

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

        Route::bind('user', function (string $value): User|string {
            request()->attributes->set('username_raw', $value);
            request()->attributes->set('username_normalized', UsernameNormalizer::normalize($value));

            $route = request()->route();

            if ($route?->getName() === 'profile.show' || $route?->getAction('livewire_component') === 'pages.profile.show') {
                return $value;
            }

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

    /**
     * @param  array<string, mixed>  $headers
     */
    private function socialFollowLimitResponse(Request $request, array $headers): Response
    {
        $message = "You're following a lot of new accounts. Take a break and come back in an hour.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 429, $headers);
        }

        return back()
            ->with('error', $message)
            ->withHeaders($headers);
    }

    private function configureDatabasePerformanceMonitoring(): void
    {
        $auditor = $this->app->make(DatabaseQueryPerformanceService::class);
        $logChannel = (string) config('database-performance.log_channel', 'performance');
        $logAllQueries = (bool) config('database-performance.listener.log_all_queries', false);
        $slowQueryMs = (float) config('database-performance.listener.slow_query_ms', 100);
        $collectBacktrace = (bool) config('database-performance.audit.collect_backtrace', true);

        DB::listen(function (QueryExecuted $query) use ($auditor, $logChannel, $logAllQueries, $slowQueryMs, $collectBacktrace): void {
            $request = request();

            if (! $request instanceof Request) {
                return;
            }

            $route = $request->route();
            $routeName = $route?->getName();
            $routeAction = $route?->getActionName();
            $routeLivewireComponent = (string) ($route?->getAction('livewire_component') ?? '');
            $livewireComponent = $routeLivewireComponent !== ''
                ? $routeLivewireComponent
                : $this->resolveLivewireComponentFromBacktrace($collectBacktrace);

            $queryContext = $collectBacktrace ? $this->queryContextFromBacktrace() : [];

            if ($this->isDatabaseAuditingEnabled()) {
                $auditor->captureQuery($query, [
                    'in_blade' => $this->isBladeExecution($queryContext),
                    'in_livewire_render' => $this->isLivewireRenderExecution($queryContext),
                    'location_signature' => (string) ($queryContext['location_signature'] ?? ''),
                ]);
            }

            if ($logAllQueries || $query->time >= $slowQueryMs) {
                $message = $logAllQueries ? 'Database query executed' : 'Slow query threshold exceeded';
                $payload = [
                    'request_id' => $this->requestIdForLog($request),
                    'sql' => (string) $query->sql,
                    'time_ms' => (float) $query->time,
                    'connection' => (string) $query->connectionName,
                    'route' => $routeName,
                    'route_action' => $routeAction,
                    'livewire_component' => $livewireComponent,
                    'path' => $request->path(),
                    'binding_types' => $this->bindingTypesForLog($query->bindings),
                    'bindings_count' => is_array($query->bindings) ? count($query->bindings) : 0,
                ];

                if ($logAllQueries && $slowQueryMs > 0.0) {
                    $payload['slow_query_threshold_ms'] = $slowQueryMs;
                }

                if ($query->time >= $slowQueryMs) {
                    Log::channel($logChannel)->warning($message, $payload);
                } else {
                    Log::channel($logChannel)->info($message, $payload);
                }
            }
        });

        if (! (bool) config('database-performance.cumulative.enabled', true)) {
            return;
        }

        $maxTotalMs = (int) config('database-performance.cumulative.slow_total_ms', 500);

        DB::whenQueryingForLongerThan($maxTotalMs, function (Connection $connection, QueryExecuted $event) use ($logChannel): void {
            $request = request();

            Log::channel($logChannel)->warning('Cumulative query time threshold exceeded', [
                'request_id' => $request instanceof Request ? $this->requestIdForLog($request) : '',
                'connection' => $connection->getName(),
                'route' => $request instanceof Request ? $request->route()?->getName() : null,
                'path' => $request instanceof Request ? $request->path() : null,
                'livewire_component' => $request instanceof Request
                    ? (string) ($request->route()?->getAction('livewire_component') ?? '')
                    : '',
                'last_query_ms' => (float) $event->time,
            ]);
        });
    }

    private function requestIdForLog(Request $request): string
    {
        if (! method_exists($request, 'id')) {
            return '';
        }

        return (string) $request->id();
    }

    private function isDatabaseAuditingEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('database-performance.enabled', true)
            && (bool) config('database-performance.listener.enabled', true)
            && (bool) config('database-performance.audit.enabled', true);
    }

    /**
     * @param  array<mixed>  $bindings
     * @return list<string>
     */
    private function bindingTypesForLog(array $bindings): array
    {
        return array_map(
            function (mixed $binding): string {
                if ($binding === null) {
                    return 'null';
                }

                if (is_bool($binding)) {
                    return 'bool';
                }

                if (is_int($binding)) {
                    return 'int';
                }

                if (is_float($binding)) {
                    return 'float';
                }

                if (is_string($binding)) {
                    return 'string';
                }

                if (is_array($binding)) {
                    return 'array';
                }

                if (is_object($binding)) {
                    return 'object:'.$binding::class;
                }

                return gettype($binding);
            },
            $bindings,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function queryContextFromBacktrace(): array
    {
        if (! (bool) config('database-performance.audit.collect_backtrace', true)) {
            return [];
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        $signature = '';
        $inBlade = false;
        $inLivewireRender = false;

        foreach ($trace as $frame) {
            $file = (string) ($frame['file'] ?? '');
            if ($file === '') {
                continue;
            }

            if ($signature === '') {
                $signature = $file.':'.((string) ($frame['line'] ?? '0'));
            }

            if (
                str_contains($file, '/resources/views/')
                || str_contains($file, 'vendor/laravel/framework/src/Illuminate/View')
            ) {
                $inBlade = true;
            }

            if (str_contains($file, 'Livewire') && str_contains((string) ($frame['function'] ?? ''), 'render')) {
                $inLivewireRender = true;
            }
        }

        return [
            'location_signature' => $signature,
            'in_blade' => $inBlade,
            'in_livewire_render' => $inLivewireRender,
        ];
    }

    private function isBladeExecution(array $queryContext): bool
    {
        return (bool) ($queryContext['in_blade'] ?? false);
    }

    private function isLivewireRenderExecution(array $queryContext): bool
    {
        return (bool) ($queryContext['in_livewire_render'] ?? false);
    }

    private function resolveLivewireComponentFromBacktrace(bool $collectBacktrace): string
    {
        if (! $collectBacktrace) {
            return '';
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40);

        foreach ($trace as $frame) {
            $class = (string) ($frame['class'] ?? '');
            if (str_starts_with($class, 'App\\Livewire\\')) {
                return $class;
            }
        }

        return '';
    }
}
