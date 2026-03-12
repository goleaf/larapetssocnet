<?php

namespace App\Actions\Users;

use App\Models\User;

class BuildProfileSettingsViewDataAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $user->loadMissing('media');

        $avatarUrl = $user->avatar_url;
        $hasAvatar = $avatarUrl !== null
            && $avatarUrl !== ''
            && $avatarUrl !== '/images/default-avatar.png';
        $coverUrl = $user->coverImageUrl();

        return [
            'user' => $user,
            'avatarUrl' => $avatarUrl,
            'hasAvatar' => $hasAvatar,
            'coverUrl' => $coverUrl,
            'hasCover' => $coverUrl !== null && $coverUrl !== '',
        ];
    }
}
