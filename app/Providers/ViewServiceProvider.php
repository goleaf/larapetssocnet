<?php

namespace App\Providers;

use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('layouts.navigation', function ($view): void {
            $user = Auth::user();

            $unreadMessageCount = 0;

            if ($user) {
                $cacheKey = 'msg_unread:'.$user->getKey();

                $unreadMessageCount = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user): int {
                    return (int) Message::query()
                        ->unread((int) $user->getKey())
                        ->count();
                });
            }

            $view->with('unreadMessageCount', $unreadMessageCount);
        });
    }
}
