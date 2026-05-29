<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Exceptions\CannotFollowSelfException;
use App\Exceptions\UserBannedException;
use App\Exceptions\UserBlockedException;
use App\Http\Middleware\BannedUserMiddleware;
use App\Http\Middleware\EnsureAccountCanAccessApplication;
use App\Http\Middleware\EnsureTwoFactorChallengeSatisfied;
use App\Http\Middleware\RedirectIfOnboardingComplete;
use App\Http\Middleware\RunRealtimeMaintenance;
use App\Http\Middleware\TrackLastSeen;
use App\Models\Identity\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RunRealtimeMaintenance::class,
        ]);

        $middleware->group('auth.verified', [
            'auth',
            'verified',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'banned' => BannedUserMiddleware::class,
            'active_account' => EnsureAccountCanAccessApplication::class,
            'two_factor' => EnsureTwoFactorChallengeSatisfied::class,
            'track_last_seen' => TrackLastSeen::class,
            'onboarding.incomplete' => RedirectIfOnboardingComplete::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($request->routeIs('register', 'login')) {
                return $user instanceof User && ! $user->hasCompletedOnboarding()
                    ? route('onboarding.show')
                    : route('feed.index');
            }

            if ($user === null) {
                return route('dashboard');
            }

            if (! empty($user->is_banned)) {
                return route('banned');
            }

            if ($user->scheduled_deletion_at !== null || $user->hasAccountStatus(AccountStatus::PendingDeletion)) {
                return route('account.deletion-pending');
            }

            if ($user->deactivated_at !== null || $user->hasAccountStatus(AccountStatus::Deactivated)) {
                return route('account.reactivation');
            }

            $suspendedUntil = $user->getAttribute('suspended_until');

            if ($user->hasAccountStatus(AccountStatus::Suspended) || ($suspendedUntil instanceof CarbonInterface && $suspendedUntil->isFuture())) {
                return route('account.suspended');
            }

            return $user->hasVerifiedEmail()
                ? route('dashboard')
                : route('verification.notice');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            $expires = $request->query('expires');

            if ($request->routeIs('verification.verify') && is_numeric($expires) && (int) $expires < time()) {
                return redirect()->route('verification.notice')->with('status', 'verification-link-expired');
            }
        });

        $exceptions->render(function (CannotFollowSelfException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->statusCode);
        });

        $exceptions->render(function (UserBlockedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->statusCode);
        });

        $exceptions->render(function (UserBannedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->statusCode);
        });
    })->create();

$basePath = $app->basePath();
$parentPath = dirname($basePath);
$configuredPublicPath = $_SERVER['LARAVEL_PUBLIC_PATH'] ?? $_ENV['LARAVEL_PUBLIC_PATH'] ?? null;

$publicPath = match (true) {
    is_string($configuredPublicPath) && $configuredPublicPath !== '' => $configuredPublicPath,
    basename($basePath) === 'laravel' && is_file($parentPath.'/index.php') => $parentPath,
    default => $basePath,
};

$app->usePublicPath($publicPath);

return $app;
