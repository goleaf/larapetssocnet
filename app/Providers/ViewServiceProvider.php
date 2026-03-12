<?php

namespace App\Providers;

use App\Enums\GroupMemberStatus;
use App\Models\Contest;
use App\Models\Event;
use App\Models\Group;
use App\Models\Hashtag;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

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
        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();
            $currentRoute = request()->route()?->getName();
            $trendingHashtags = collect();
            $upcomingEvents = collect();
            $suggestedUsers = collect();
            $activeContests = collect();
            $yourGroups = collect();
            $communityStats = [
                ['label' => 'Members', 'value' => '--'],
                ['label' => 'Pets', 'value' => '--'],
                ['label' => 'Posts', 'value' => '--'],
            ];

            try {
                if (Schema::hasTable('users')) {
                    $communityStats[0]['value'] = number_format((int) User::query()->count());
                }

                if (Schema::hasTable('pets')) {
                    $communityStats[1]['value'] = number_format((int) Pet::query()->count());
                }

                if (Schema::hasTable('posts')) {
                    $communityStats[2]['value'] = number_format((int) Post::query()->count());
                }

                if (Schema::hasTable('hashtags')) {
                    $trendingHashtags = Hashtag::query()
                        ->select(['id', 'name', 'slug', 'posts_count'])
                        ->orderByDesc('posts_count')
                        ->limit(5)
                        ->get();
                }

                if (Schema::hasTable('events')) {
                    $upcomingEvents = Event::query()
                        ->select(['id', 'title', 'start_at', 'location_text', 'attendees_count'])
                        ->where('start_at', '>=', now())
                        ->orderBy('start_at')
                        ->limit(2)
                        ->get();
                }

                if ($user instanceof User) {
                    $suggestedUsers = $this->resolveSuggestedUsers($user);
                    $yourGroups = $this->resolveUserGroups($user);
                }

                if (Schema::hasTable('contests')) {
                    $activeContests = Contest::query()
                        ->select(['id', 'title', 'slug', 'status', 'ends_at', 'entries_count'])
                        ->whereIn('status', ['active', 'voting'])
                        ->orderBy('ends_at')
                        ->limit(2)
                        ->get();
                }
            } catch (Throwable) {
                // Keep the layout resilient during migrations and test setup.
            }

            $view->with([
                'appName' => config('app.name', 'LaraPets'),
                'user' => $user,
                'isAuthenticated' => $user !== null,
                'searchTarget' => Route::has('search.index') ? route('search.index') : url('/'),
                'desktopNav' => $this->resolveDesktopNavItems($user, $currentRoute),
                'mobileNav' => $this->resolveMobileNavItems($user, $currentRoute),
                'hideLeftRail' => $this->resolveHideLeftRail($currentRoute),
                'communityStats' => $communityStats,
                'trendingHashtags' => $trendingHashtags,
                'upcomingEvents' => $upcomingEvents,
                'suggestedUsers' => $suggestedUsers,
                'activeContests' => $activeContests,
                'yourGroups' => $yourGroups,
            ]);
        });
    }

    private function resolveSuggestedUsers(User $user): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        return User::query()
            ->select(['id', 'name', 'username', 'avatar_path', 'followers_count'])
            ->whereKeyNot($user->getKey())
            ->where('is_private', false)
            ->where('is_banned', false)
            ->orderByDesc('followers_count')
            ->limit(3)
            ->get();
    }

    private function resolveUserGroups(User $user): Collection
    {
        if (! Schema::hasTable('groups') || ! Schema::hasTable('group_members')) {
            return collect();
        }

        return Group::query()
            ->select(['groups.id', 'groups.name', 'groups.slug', 'groups.privacy', 'groups.members_count'])
            ->whereIn('groups.id', function ($query) use ($user): void {
                $query->select('group_members.group_id')
                    ->from('group_members')
                    ->where('group_members.user_id', $user->getKey())
                    ->where(function ($statusQuery): void {
                        $statusQuery->whereNull('group_members.status')
                            ->orWhereIn('group_members.status', GroupMemberStatus::activeValues());
                    });
            })
            ->orderByDesc('groups.members_count')
            ->limit(6)
            ->get();
    }

    /**
     * @return array<int, array{label:string,href:string,icon:string,active:bool}>
     */
    private function resolveDesktopNavItems(?User $user, ?string $currentRoute): array
    {
        $isAuthenticated = $user !== null;
        $items = [
            [
                'label' => $isAuthenticated ? 'Feed' : 'Explore Feed',
                'icon' => '🏠',
                'route' => $isAuthenticated ? 'feed.index' : 'explore.index',
                'patterns' => $isAuthenticated ? ['feed.*', 'posts.*', 'saved.*'] : ['explore.*', 'search.*', 'hashtags.*'],
                'exclude' => [],
            ],
            [
                'label' => 'Explore',
                'icon' => '🧭',
                'route' => 'explore.index',
                'patterns' => ['explore.*', 'search.*', 'hashtags.*'],
                'exclude' => [],
            ],
            [
                'label' => 'Pets',
                'icon' => '🐾',
                'route' => 'pets.explore',
                'patterns' => ['pets.*', 'tips.*'],
                'exclude' => ['pets.adopt'],
            ],
            [
                'label' => 'Adopt',
                'icon' => '🏡',
                'route' => 'pets.adopt',
                'patterns' => ['pets.adopt'],
                'exclude' => [],
            ],
            [
                'label' => 'Groups',
                'icon' => '👥',
                'route' => 'groups.index',
                'patterns' => ['groups.*'],
                'exclude' => [],
            ],
            [
                'label' => 'Events',
                'icon' => '📅',
                'route' => 'events.index',
                'patterns' => ['events.*'],
                'exclude' => [],
            ],
            [
                'label' => 'Marketplace',
                'icon' => '🛍️',
                'route' => 'marketplace.index',
                'patterns' => ['marketplace.*', 'messages.*'],
                'exclude' => [],
            ],
        ];

        return array_map(function (array $item) use ($currentRoute): array {
            $route = (string) ($item['route'] ?? '');

            return [
                'label' => (string) $item['label'],
                'href' => $route !== '' && Route::has($route) ? route($route) : '#',
                'icon' => '<span class="text-lg leading-none">'.$item['icon'].'</span>',
                'active' => $this->routeIsActive(
                    $currentRoute,
                    (array) ($item['patterns'] ?? []),
                    (array) ($item['exclude'] ?? []),
                ),
            ];
        }, $items);
    }

    /**
     * @return array<int, array{label:string,href:string,icon:string,active:bool,isPrimaryAction:bool}>
     */
    private function resolveMobileNavItems(?User $user, ?string $currentRoute): array
    {
        $isAuthenticated = $user !== null;
        $items = [
            [
                'label' => 'Home',
                'icon' => '🏠',
                'route' => $isAuthenticated ? 'feed.index' : 'explore.index',
                'patterns' => $isAuthenticated ? ['feed.*', 'posts.*'] : ['explore.*', 'search.*', 'hashtags.*'],
            ],
            [
                'label' => 'Explore',
                'icon' => '🧭',
                'route' => 'explore.index',
                'patterns' => ['explore.*', 'search.*'],
            ],
            [
                'label' => 'Post',
                'icon' => '✚',
                'route' => 'posts.create',
                'patterns' => ['posts.create'],
            ],
            [
                'label' => 'Groups',
                'icon' => '👥',
                'route' => 'groups.index',
                'patterns' => ['groups.*'],
            ],
            [
                'label' => 'Profile',
                'icon' => '🙂',
                'route' => 'settings.profile',
                'patterns' => ['profile.*', 'settings.*'],
            ],
        ];

        return array_map(function (array $item) use ($currentRoute): array {
            $route = (string) ($item['route'] ?? '');

            return [
                'label' => (string) $item['label'],
                'href' => $route !== '' && Route::has($route) ? route($route) : '#',
                'icon' => (string) $item['icon'],
                'active' => $this->routeIsActive($currentRoute, (array) ($item['patterns'] ?? [])),
                'isPrimaryAction' => $route === 'posts.create',
            ];
        }, $items);
    }

    private function resolveHideLeftRail(?string $currentRoute): bool
    {
        return $this->routeIsActive($currentRoute, [
            'profile.show',
            'profile.followers',
            'profile.following',
            'profile.edit',
            'profile.update',
            'settings.*',
            'settings.profile.*',
            'pets.show',
            'pets.edit',
            'pets.update',
            'pets.create',
        ]);
    }

    /**
     * @param  array<int, string>  $patterns
     * @param  array<int, string>  $except
     */
    private function routeIsActive(?string $currentRoute, array $patterns, array $except = []): bool
    {
        if (! is_string($currentRoute) || $currentRoute === '') {
            return false;
        }

        foreach ($except as $pattern) {
            if (Str::is($pattern, $currentRoute)) {
                return false;
            }
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $currentRoute)) {
                return true;
            }
        }

        return false;
    }
}
