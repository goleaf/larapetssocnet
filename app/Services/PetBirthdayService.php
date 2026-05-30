<?php

namespace App\Services;

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Notifications\Database\Pets\PetBirthdayCoOwnerNotification;
use App\Notifications\Database\Pets\PetBirthdayFollowerNotification;
use App\Support\Posts\PostCreationInput;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PetBirthdayService
{
    public function __construct(private readonly CreatePostAction $createPost) {}

    public function process(int $petId): void
    {
        $pet = Pet::query()
            ->with([
                'owner' => fn ($query) => $query->select(['users.id', 'users.name', 'users.username', 'users.notification_preferences']),
                'ownerships' => fn ($query) => $query->select(['id', 'pet_id', 'user_id', 'accepted_at']),
                'ownerships.user' => fn ($query) => $query->select(['users.id', 'users.name', 'users.username', 'users.notification_preferences']),
            ])
            ->select(['id', 'user_id', 'name', 'slug', 'visibility', 'is_public', 'birth_date', 'date_of_birth', 'birth_year', 'is_archived'])
            ->whereKey($petId)
            ->whereNull('deleted_at')
            ->first();

        if (! $pet instanceof Pet || (bool) $pet->getAttribute('is_archived') || ! $pet->owner instanceof User) {
            return;
        }

        $age = $this->ageFor($pet);
        $message = str_replace(
            ['{pet}', '{age}'],
            [$pet->name, (string) $age],
            Arr::random((array) config('pets.birthday.post_templates', [
                '{pet} turns {age} today!',
                'It is {pet} birthday. They turn {age} today!',
            ]))
        );

        $this->createPost->handle($pet->owner, PostCreationInput::fromUserInput($pet->owner, [
            'body' => $message,
            'pet_id' => $pet->getKey(),
            'tagged_pets' => [$pet->getKey()],
            'status' => PostStatus::Published,
            'visibility' => Post::VISIBILITY_PUBLIC,
            'is_system_generated' => true,
            'confirmed_duplicate' => true,
            'system_source' => 'pet_birthday',
            'metadata' => [
                'source' => 'pet_birthday',
                'birthday_age' => $age,
            ],
        ]));

        $pet->followers()
            ->select(['users.id', 'users.name', 'users.email', 'users.notification_preferences'])
            ->orderBy('users.id')
            ->chunk(100, function ($followers) use ($pet, $age): void {
                $eligible = $followers->filter(fn (User $user): bool => $this->acceptsBirthdayNotifications($user));

                if ($eligible->isNotEmpty()) {
                    Notification::send($eligible, new PetBirthdayFollowerNotification($pet, $age));
                }
            });

        $coOwners = $pet->ownerships
            ->filter(fn ($ownership): bool => (int) $ownership->user_id !== (int) $pet->user_id)
            ->map(fn ($ownership) => $ownership->user)
            ->filter(fn ($user): bool => $user instanceof User)
            ->values();

        $followingCoOwnerIds = $this->followingPetUserIds($pet, $coOwners);

        $coOwners = $coOwners
            ->filter(fn (User $user): bool => isset($followingCoOwnerIds[(int) $user->getKey()]))
            ->values();

        if ($coOwners->isNotEmpty()) {
            Notification::send($coOwners, new PetBirthdayCoOwnerNotification($pet, $age));
        }
    }

    private function ageFor(Pet $pet): int
    {
        $birthDate = $pet->getAttribute('date_of_birth') ?? $pet->getAttribute('birth_date');

        if ($birthDate instanceof CarbonInterface) {
            return max(0, (int) $birthDate->age);
        }

        if (is_string($birthDate) && $birthDate !== '') {
            return max(0, (int) CarbonImmutable::parse($birthDate)->age);
        }

        $birthYear = (int) ($pet->birth_year ?? 0);

        return $birthYear > 0 ? max(0, now()->year - $birthYear) : 0;
    }

    private function acceptsBirthdayNotifications(User $user): bool
    {
        $preferences = $user->notification_preferences;

        if (is_string($preferences)) {
            $preferences = json_decode($preferences, true);
        }

        if (! is_array($preferences)) {
            return true;
        }

        return ($preferences['pet_birthdays'] ?? true) !== false;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, bool>
     */
    private function followingPetUserIds(Pet $pet, Collection $users): array
    {
        $userIds = $users
            ->map(fn (User $user): int => (int) $user->getKey())
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return DB::table('pet_followers')
            ->where('pet_id', $pet->getKey())
            ->whereIn('user_id', $userIds->all())
            ->pluck('user_id')
            ->mapWithKeys(fn (int|string $userId): array => [(int) $userId => true])
            ->all();
    }
}
