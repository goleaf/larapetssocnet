<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    /**
     * @var list<string>
     */
    private const SPECIES = [
        'Dog',
        'Cat',
        'Bird',
        'Rabbit',
        'Fish',
        'Reptile',
        'Hamster',
        'Other',
    ];

    public function show(Request $request, int $step): View|RedirectResponse
    {
        $user = $request->user();
        $step = $this->normalizeStep($step);

        if ($this->isCompleted($user)) {
            return redirect()->route('dashboard');
        }

        $currentStep = $this->currentStep($user);
        if ($step > $currentStep) {
            return redirect()->route('onboarding.show', ['step' => $currentStep]);
        }

        $this->ensureSuggestedGroups();

        $suggestedUsers = collect();
        $suggestedGroups = collect();
        $followingIds = [];
        $joinedGroupIds = [];

        if ($step === 3) {
            $suggestedUsers = User::query()
                ->active()
                ->withPublicProfile()
                ->whereKeyNot($user->id)
                ->notBlockedFor($user)
                ->orderByDesc('followers_count')
                ->limit(8)
                ->withCount(['followers', 'following'])
                ->get();

            $suggestedGroups = Group::query()
                ->withCount('members')
                ->orderByDesc('members_count')
                ->limit(8)
                ->get();

            $followingIds = $user->following()->pluck('users.id')->all();
            $joinedGroupIds = $user->groups()->pluck('groups.id')->all();
        }

        return view("onboarding.step{$step}", [
            'step' => $step,
            'speciesOptions' => self::SPECIES,
            'selectedInterests' => $this->selectedInterests($user),
            'suggestedUsers' => $suggestedUsers,
            'suggestedGroups' => $suggestedGroups,
            'followingIds' => $followingIds,
            'joinedGroupIds' => $joinedGroupIds,
        ]);
    }

    public function store(Request $request, int $step): RedirectResponse
    {
        $user = $request->user();
        $step = $this->normalizeStep($step);

        if ($this->isCompleted($user)) {
            return redirect()->route('dashboard');
        }

        $currentStep = $this->currentStep($user);
        if ($step > $currentStep) {
            return redirect()->route('onboarding.show', ['step' => $currentStep]);
        }

        if ($step === 1) {
            $validated = $request->validate([
                'interests' => ['nullable', 'array'],
                'interests.*' => ['string', Rule::in(self::SPECIES)],
            ]);

            $interests = collect($validated['interests'] ?? [])->unique()->values()->all();
            $user->interests_text = empty($interests) ? null : implode(', ', $interests);
            $user->save();
        }

        if ($step === 2) {
            $validated = $request->validate([
                'pet_name' => ['nullable', 'string', 'max:120'],
                'pet_species' => ['nullable', 'string', Rule::in(self::SPECIES)],
                'pet_bio' => ['nullable', 'string', 'max:1000'],
            ]);

            if (! empty($validated['pet_name']) && ! empty($validated['pet_species'])) {
                $user->pets()->create([
                    'name' => $validated['pet_name'],
                    'species' => $validated['pet_species'],
                    'bio' => $validated['pet_bio'] ?: null,
                ]);
            }
        }

        if ($step === 3) {
            $validated = $request->validate([
                'follow_user_ids' => ['nullable', 'array'],
                'follow_user_ids.*' => ['integer', 'exists:users,id'],
                'join_group_ids' => ['nullable', 'array'],
                'join_group_ids.*' => ['integer', 'exists:groups,id'],
            ]);

            $followUserIds = collect($validated['follow_user_ids'] ?? [])
                ->filter(fn ($userId): bool => (int) $userId !== $user->id)
                ->unique()
                ->values();

            if ($followUserIds->isNotEmpty()) {
                User::query()
                    ->whereIn('id', $followUserIds->all())
                    ->notBlockedFor($user)
                    ->each(fn (User $suggestedUser) => $user->follow($suggestedUser));
            }

            $joinGroupIds = collect($validated['join_group_ids'] ?? [])->unique()->values();
            if ($joinGroupIds->isNotEmpty()) {
                Group::query()
                    ->whereIn('id', $joinGroupIds->all())
                    ->each(function (Group $group) use ($user): void {
                        if (method_exists($group, 'addMember')) {
                            $group->addMember($user);

                            return;
                        }

                        $user->groups()->syncWithoutDetaching([$group->id]);
                    });
            }
        }

        return $this->moveToNextStep($user, $step);
    }

    public function skip(Request $request, int $step): RedirectResponse
    {
        $user = $request->user();
        $step = $this->normalizeStep($step);

        if ($this->isCompleted($user)) {
            return redirect()->route('dashboard');
        }

        $currentStep = $this->currentStep($user);
        if ($step > $currentStep) {
            return redirect()->route('onboarding.show', ['step' => $currentStep]);
        }

        return $this->moveToNextStep($user, $step);
    }

    protected function moveToNextStep(User $user, int $step): RedirectResponse
    {
        if ($step >= 3) {
            $payload = ['onboarding_step' => 'completed'];

            if (Schema::hasColumn('users', 'onboarding_completed_at')) {
                $payload['onboarding_completed_at'] = now();
            }

            $user->forceFill($payload)->save();

            return redirect()
                ->route('dashboard')
                ->with('status', 'onboarding-complete');
        }

        $nextStep = $step + 1;

        $user->forceFill([
            'onboarding_step' => (string) $nextStep,
        ])->save();

        return redirect()->route('onboarding.show', ['step' => $nextStep]);
    }

    protected function currentStep(User $user): int
    {
        $rawStep = (string) ($user->onboarding_step ?? '1');

        if (is_numeric($rawStep)) {
            return max(1, min((int) $rawStep, 3));
        }

        return match ($rawStep) {
            'step2', 'pet' => 2,
            'step3', 'social' => 3,
            'completed', 'done' => 3,
            default => 1,
        };
    }

    protected function isCompleted(User $user): bool
    {
        if (Schema::hasColumn('users', 'onboarding_completed_at') && $user->onboarding_completed_at !== null) {
            return true;
        }

        return in_array((string) $user->onboarding_step, ['completed', 'done', '4'], true);
    }

    protected function normalizeStep(int $step): int
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        return $step;
    }

    /**
     * @return list<string>
     */
    protected function selectedInterests(User $user): array
    {
        if (! $user->interests_text) {
            return [];
        }

        return collect(explode(',', $user->interests_text))
            ->map(fn (string $interest): string => trim($interest))
            ->filter()
            ->values()
            ->all();
    }

    protected function ensureSuggestedGroups(): void
    {
        if (Group::query()->exists()) {
            return;
        }

        Group::query()->create([
            'name' => 'Dog Parents',
            'slug' => 'dog-parents',
            'description' => 'Daily dog stories, training wins, and local meetup tips.',
        ]);

        Group::query()->create([
            'name' => 'Cat Corner',
            'slug' => 'cat-corner',
            'description' => 'Everything from rescue stories to indoor enrichment ideas.',
        ]);

        Group::query()->create([
            'name' => 'Small Pets Club',
            'slug' => 'small-pets-club',
            'description' => 'Tips and support for rabbits, hamsters, and other tiny companions.',
        ]);

        Group::query()->create([
            'name' => 'Bird Lovers',
            'slug' => 'bird-lovers',
            'description' => 'Care guides, habitat setups, and fun behavior snapshots.',
        ]);
    }
}
