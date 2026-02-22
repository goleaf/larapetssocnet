<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SettingsService
{
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $avatar): User {
            $user->update(array_filter([
                'name' => $data['name'] ?? null,
                'username' => $data['username'] ?? null,
                'bio' => $data['bio'] ?? null,
                'bio_html' => isset($data['bio']) && class_exists(ContentService::class)
                    ? app(ContentService::class)->process($data['bio'])
                    : null,
                'location' => $data['location'] ?? null,
                'website' => $data['website'] ?? null,
            ], fn ($v) => $v !== null));

            if ($avatar) {
                $user->clearMediaCollection('avatar');
                $user->addMedia($avatar)
                    ->usingFileName(Str::uuid().'.webp')
                    ->toMediaCollection('avatar');
            }

            return $user->fresh();
        });
    }

    public function changePassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw new RuntimeException('Current password is incorrect.');
        }

        $user->update(['password' => Hash::make($new)]);
    }

    public function updatePrivacy(User $user, bool $isPrivate): void
    {
        $user->update(['is_private' => $isPrivate]);

        if (! $isPrivate) {
            $pending = Follow::query()
                ->where('following_id', $user->getKey())
                ->where('status', 'pending')
                ->get();

            foreach ($pending as $follow) {
                $follow->update(['status' => 'accepted']);
                $user->increment('followers_count');
                User::where('id', $follow->follower_id)->increment('following_count');
            }
        }
    }

    public function deleteAccount(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new RuntimeException('Password is incorrect.');
        }

        DB::transaction(function () use ($user): void {
            $user->posts()->each(fn (Post $p) => $p->delete());
            $user->pets()->each(fn (Pet $p) => $p->delete());
            $user->delete();
        });

        Auth::logout();
    }
}
