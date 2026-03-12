<?php

namespace App\Services;

use App\Models\User;

class AccountExportService
{
    /**
     * Compile a comprehensive export of the user's data.
     */
    public function exportData(User $user): array
    {
        $user->load([
            'pets',
            'posts.media',
            'ownedGroups',
            'groups',
            'notifications',
        ]);

        return [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'username' => $user->username,
                'email' => $user->email,
                'bio' => $user->bio,
                'headline' => $user->headline,
                'pronouns' => $user->pronouns,
                'location' => $user->location,
                'city' => $user->city,
                'country_code' => $user->country_code,
                'website' => $user->website,
                'social_links' => $user->social_links,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'profile_theme' => $user->profile_theme,
                'birth_date' => $user->birth_date ? $user->birth_date->format('Y-m-d') : null,
                'gender' => $user->gender,
                'created_at' => $user->created_at->toIso8601String(),
                'settings' => [
                    'profile_visibility' => $user->profile_visibility,
                    'messaging_permission' => $user->messaging_permission,
                    'pets_visibility' => $user->pets_visibility,
                    'groups_visibility' => $user->groups_visibility,
                    'show_in_explore' => $user->show_in_explore,
                    'open_following' => $user->open_following,
                    'notification_preferences' => $user->notification_preferences,
                ],
            ],
            'pets' => $user->pets->map(function ($pet) {
                return [
                    'id' => $pet->id,
                    'name' => $pet->name,
                    'type' => $pet->type,
                    'breed' => $pet->breed,
                    'birth_date' => $pet->birth_date ? $pet->birth_date->format('Y-m-d') : null,
                    'bio' => $pet->bio,
                    'created_at' => $pet->created_at->toIso8601String(),
                ];
            })->toArray(),
            'posts' => $user->posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'content' => $post->content,
                    'visibility' => $post->visibility,
                    'created_at' => $post->created_at->toIso8601String(),
                ];
            })->toArray(),
            'owned_groups' => $user->ownedGroups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'created_at' => $group->created_at->toIso8601String(),
                ];
            })->toArray(),
            'joined_groups' => $user->groups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'joined_at' => $group->pivot->joined_at ?? null,
                    'role' => $group->pivot->role ?? 'member',
                ];
            })->toArray(),
            'notifications' => $user->notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at ? $notification->read_at->toIso8601String() : null,
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            })->toArray(),
            'followers_count' => $user->followers_count,
            'following_count' => $user->following_count,
            'blocked_users_count' => $user->blocking()->count(),
        ];
    }
}
