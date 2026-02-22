<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\Group;
use App\Models\Hashtag;
use App\Models\Pet;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminService
{
    /** @return array<string, mixed> */
    public function getStats(): array
    {
        return [
            'users_total' => User::count(),
            'users_today' => User::whereDate('created_at', today())->count(),
            'users_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            'posts_total' => Post::count(),
            'posts_today' => Post::whereDate('created_at', today())->count(),
            'pets_total' => Pet::count(),
            'groups_total' => Group::count(),
            'contests_active' => Contest::active()->count(),
            'reports_pending' => Report::where('status', 'pending')->count(),
            'top_hashtags' => Hashtag::orderByDesc('posts_count')
                ->limit(5)->get(['name', 'posts_count']),
        ];
    }

    public function banUser(User $user, User $admin): void
    {
        if ($user->hasAppRole('admin')) {
            throw new \RuntimeException('Cannot ban an admin user.');
        }

        $user->update(['is_banned' => true]);
    }

    public function unbanUser(User $user, User $admin): void
    {
        $user->update(['is_banned' => false]);
    }

    public function changeRole(User $user, string $role, User $admin): void
    {
        if (! in_array($role, ['member', 'moderator', 'admin'], true)) {
            throw new \RuntimeException("Invalid role: {$role}");
        }

        $user->update(['role' => $role]);
    }

    public function softDeleteUser(User $user, User $admin): void
    {
        DB::transaction(function () use ($user): void {
            $user->posts()->each(fn (Post $p) => $p->delete());
            $user->pets()->each(fn (Pet $p) => $p->delete());
            $user->delete();
        });
    }
}
