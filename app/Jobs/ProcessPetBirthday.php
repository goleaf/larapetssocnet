<?php

namespace App\Jobs;

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Notifications\PetBirthdayCoOwnerNotification;
use App\Notifications\PetBirthdayFollowerNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;

class ProcessPetBirthday implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $petId) {}

    public function handle(CreatePostAction $createPost): void
    {
        $pet = Pet::query()
            ->with(['owner', 'ownerships.user'])
            ->select(['id', 'user_id', 'name', 'slug', 'visibility', 'is_public', 'birth_date', 'date_of_birth', 'birth_year', 'is_archived'])
            ->whereKey($this->petId)
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

        $createPost->handle($pet->owner, [
            'body' => $message,
            'pet_id' => $pet->getKey(),
            'tagged_pets' => [$pet->getKey()],
            'status' => PostStatus::Published,
            'visibility' => Post::VISIBILITY_PUBLIC,
            'is_system_generated' => true,
            'system_source' => 'pet_birthday',
            'metadata' => [
                'source' => 'pet_birthday',
                'birthday_age' => $age,
            ],
        ]);

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
            ->filter(fn ($user): bool => $user instanceof User && $pet->isFollowedBy($user))
            ->values();

        if ($coOwners->isNotEmpty()) {
            Notification::send($coOwners, new PetBirthdayCoOwnerNotification($pet, $age));
        }
    }

    private function ageFor(Pet $pet): int
    {
        $birthDate = $pet->date_of_birth ?? $pet->birth_date;

        if ($birthDate) {
            return max(0, (int) $birthDate->age);
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
}
