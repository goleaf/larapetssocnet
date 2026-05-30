<?php

namespace App\Http\Controllers\Feed;

use App\Enums\GroupMemberStatus;
use App\Http\Controllers\Controller;
use App\Services\FeedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $user->setAttribute('feed_followers_count', $user->acceptedFollowers()->count());
        $user->setAttribute('feed_following_count', $user->acceptedFollowing()->count());
        $user->setAttribute('feed_pets_count', $user->pets()->count());

        $onboardingCompletedAt = $user->onboarding_completed_at;
        $showWelcomeBanner = $onboardingCompletedAt !== null
            && Carbon::parse((string) $onboardingCompletedAt)->greaterThanOrEqualTo(now()->subDay())
            && ! $request->session()->has('onboarding_welcome_banner_dismissed');
        $showOnboardingPetReminder = (bool) $user->onboarding_pet_reminder_pending
            && $user->onboarding_pet_reminder_shown_at === null;

        if ($showOnboardingPetReminder) {
            $user->forceFill([
                'onboarding_pet_reminder_shown_at' => now(),
            ])->saveQuietly();
        }

        $ownedPets = $user->pets()
            ->without(['user', 'species', 'breed', 'media', 'tags'])
            ->select(['pets.id', 'pets.user_id', 'pets.name', 'pets.slug', 'pets.species', 'pets.breed'])
            ->orderBy('pets.name')
            ->get();

        $type = in_array($request->string('type')->toString(), ['text', 'photo', 'video'], true)
            ? $request->string('type')->toString()
            : null;

        $source = in_array($request->string('source')->toString(), ['people', 'pets'], true)
            ? $request->string('source')->toString()
            : null;

        $sidebarData = $this->feed->getSidebarData($user);

        $yourGroups = $user->groups()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('group_members.status')
                    ->orWhereIn('group_members.status', GroupMemberStatus::activeValues());
            })
            ->orderByDesc('groups.members_count')
            ->limit(6)
            ->get();

        return view('feed.index', array_merge(
            ['yourGroups' => $yourGroups, 'ownedPets' => $ownedPets],
            $sidebarData,
            [
                'user' => $user,
                'type' => $type,
                'source' => $source,
                'showWelcomeBanner' => $showWelcomeBanner,
                'showOnboardingPetReminder' => $showOnboardingPetReminder,
            ],
        ));
    }
}
