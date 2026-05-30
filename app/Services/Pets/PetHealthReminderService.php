<?php

namespace App\Services\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthReminder;
use App\Notifications\Database\Pets\PetHealthReminderDue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PetHealthReminderService
{
    public function create(User $actor, Pet $pet, string $type, int $frequencyDays, ?string $customText = null, Carbon|string|null $nextDueOn = null): PetHealthReminder
    {
        if (! $actor->can('manageHealth', $pet)) {
            throw new AuthorizationException('You are not allowed to manage health reminders for this pet.');
        }

        if (! in_array($type, PetHealthReminder::types(), true)) {
            throw $this->validation('Choose a valid reminder type.');
        }

        if ($type === PetHealthReminder::TYPE_CUSTOM && blank($customText)) {
            throw $this->validation('Custom reminders need reminder text.');
        }

        if ($frequencyDays < 1 || $frequencyDays > 730) {
            throw $this->validation('Reminder frequency must be between 1 and 730 days.');
        }

        return PetHealthReminder::query()->create([
            'pet_id' => $pet->getKey(),
            'reminder_type' => $type,
            'custom_text' => $customText,
            'frequency_days' => $frequencyDays,
            'last_sent_on' => null,
            'next_due_on' => $this->dueDate($nextDueOn),
        ]);
    }

    public function sendDueReminders(?Carbon $today = null): int
    {
        $today ??= today();
        $sent = 0;
        $processedIds = [];

        do {
            $ids = $this->dueReminderIds($today, $processedIds);

            if ($ids->isEmpty()) {
                break;
            }

            $processedIds = array_merge($processedIds, $ids->all());

            $reminders = PetHealthReminder::query()
                ->with(['pet.owner', 'pet.ownerships.user'])
                ->whereKey($ids)
                ->get();

            DB::transaction(function () use ($reminders, &$sent, $today): void {
                $reminders->each(function (PetHealthReminder $reminder) use (&$sent, $today): void {
                    $pet = $reminder->pet;

                    if (! $pet instanceof Pet || (bool) $pet->is_archived || $pet->trashed()) {
                        return;
                    }

                    $this->recipients($pet)->each(function (User $user) use ($reminder, &$sent): void {
                        $user->notify(new PetHealthReminderDue($reminder));
                        $sent++;
                    });

                    $reminder->forceFill([
                        'last_sent_on' => $today->toDateString(),
                        'next_due_on' => $today->copy()->addDays((int) $reminder->frequency_days)->toDateString(),
                    ])->save();
                });
            });
        } while ($ids->count() === 100);

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Pet $pet): Collection
    {
        return collect([$pet->owner])
            ->merge(
                $pet->ownerships
                    ->filter(fn ($ownership): bool => filled($ownership->accepted_at))
                    ->map(fn ($ownership) => $ownership->user)
            )
            ->filter(fn ($user): bool => $user instanceof User)
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->values();
    }

    private function dueDate(Carbon|string|null $date): string
    {
        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        if (is_string($date) && $date !== '') {
            return Carbon::parse($date)->toDateString();
        }

        return today()->toDateString();
    }

    /**
     * @param  list<int>  $processedIds
     * @return Collection<int, int>
     */
    private function dueReminderIds(Carbon $today, array $processedIds): Collection
    {
        return PetHealthReminder::query()
            ->where('next_due_on', '<=', $today->toDateString())
            ->when($processedIds !== [], fn ($query) => $query->whereNotIn('id', $processedIds))
            ->orderBy('next_due_on')
            ->orderBy('pet_id')
            ->limit(100)
            ->pluck('id');
    }

    private function validation(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'pet_health_reminder' => $message,
        ]);
    }
}
