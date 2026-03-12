<?php

namespace App\View\Components\Ui;

use App\Models\Message;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class Navbar extends Component
{
    public function render(): View|Closure|string
    {
        $user = Auth::user();
        $currentRoute = request()->route()?->getName();

        return view('components.ui.navbar', [
            'links' => $this->resolveLinks($currentRoute),
            'homeHref' => $this->resolveHomeHref(),
            'unreadMessageCount' => $this->resolveUnreadMessageCount($user),
            'unreadNotificationsCount' => $this->resolveUnreadNotificationsCount($user),
            'user' => $user,
        ]);
    }

    /**
     * @return array<int, array{label:string,href:string,active:bool}>
     */
    private function resolveLinks(?string $currentRoute): array
    {
        $items = [
            [
                'label' => 'Feed',
                'route' => Route::has('feed.index') ? 'feed.index' : null,
                'fallback' => '/feed',
                'patterns' => ['feed.*', 'posts.*', 'saved.*'],
            ],
            [
                'label' => 'Groups',
                'route' => Route::has('groups.index') ? 'groups.index' : null,
                'fallback' => '/groups',
                'patterns' => ['groups.*'],
            ],
            [
                'label' => 'Explore',
                'route' => Route::has('explore.index') ? 'explore.index' : null,
                'fallback' => '/explore',
                'patterns' => ['explore.*', 'search.*', 'hashtags.*'],
            ],
            [
                'label' => 'My Pets',
                'route' => Route::has('pets.explore') ? 'pets.explore' : null,
                'fallback' => '/mypets',
                'patterns' => ['pets.*'],
            ],
        ];

        return array_map(function (array $item) use ($currentRoute): array {
            $routeName = $item['route'];
            $href = is_string($routeName) && Route::has($routeName) ? route($routeName) : (string) $item['fallback'];

            return [
                'label' => (string) $item['label'],
                'href' => $href,
                'active' => $this->isRouteActive($currentRoute, (array) $item['patterns']),
            ];
        }, $items);
    }

    private function resolveHomeHref(): string
    {
        if (Auth::check() && Route::has('feed.index')) {
            return route('feed.index');
        }

        if (Route::has('explore.index')) {
            return route('explore.index');
        }

        return url('/');
    }

    private function resolveUnreadMessageCount(mixed $user): int
    {
        if (! $user instanceof User) {
            return 0;
        }

        $cacheKey = 'msg_unread:'.$user->getKey();

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user): int {
            return (int) Message::query()
                ->unread((int) $user->getKey())
                ->count();
        });
    }

    private function resolveUnreadNotificationsCount(mixed $user): int
    {
        if (! $user instanceof User || ! Schema::hasTable('notifications')) {
            return 0;
        }

        return (int) $user->unreadNotifications()->count();
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function isRouteActive(?string $currentRoute, array $patterns): bool
    {
        if (! is_string($currentRoute) || $currentRoute === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $currentRoute)) {
                return true;
            }
        }

        return false;
    }
}
