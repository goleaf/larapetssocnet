<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\Identity\User;

class BuildProfileSettingsViewDataAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $user->loadMissing('media');

        $avatarUrl = $user->getAttribute('avatar_url');
        $avatarUrl = is_string($avatarUrl) ? $avatarUrl : null;
        $hasAvatar = ! in_array($avatarUrl, [null, '', '/images/default-avatar.png'], true);
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
