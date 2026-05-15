<?php

declare(strict_types=1);

use App\Exceptions\CannotFollowSelfException;
use App\Exceptions\UserBannedException;
use App\Exceptions\UserBlockedException;
use App\Http\Middleware\BannedUserMiddleware;
use App\Http\Middleware\RunRealtimeMaintenance;
use App\Http\Middleware\TrackLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RunRealtimeMaintenance::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'banned' => BannedUserMiddleware::class,
            'track_last_seen' => TrackLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
