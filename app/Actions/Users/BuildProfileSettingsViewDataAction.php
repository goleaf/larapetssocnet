<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\ProfileTheme;
use App\Models\Identity\User;
use App\Services\ProfilePortfolioService;

class BuildProfileSettingsViewDataAction
{
    public function __construct(private readonly ProfilePortfolioService $portfolioService) {}

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
        $portfolioPosts = $this->portfolioService->manageablePosts($user);
        $portfolioPostIds = $this->portfolioService->selectedPostIds($user);

        return [
            'user' => $user,
            'avatarUrl' => $avatarUrl,
            'hasAvatar' => $hasAvatar,
            'coverUrl' => $coverUrl,
            'hasCover' => $coverUrl !== null && $coverUrl !== '',
            'portfolioPosts' => $portfolioPosts,
            'portfolioPostIds' => $portfolioPostIds,
            'portfolioPositions' => $this->portfolioService->selectedPositions($user),
            'portfolioUrl' => route('profile.portfolio', ['user' => $user->username]),
            'profileThemeOptions' => ProfileTheme::settingsOptions(),
            'selectedProfileTheme' => $user->profileTheme()->value,
        ];
    }
}
