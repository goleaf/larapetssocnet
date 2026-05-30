<?php

namespace App\Notifications\Database\Pets;

use App\Models\Pets\PetHealthReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

class PetHealthReminderDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PetHealthReminder $reminder)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $pet = $this->reminder->pet;
        $task = $this->label();
        $nextDueOn = $this->reminder->next_due_on;

        return [
            'type' => 'pet_health_reminder_due',
            'message' => "{$pet->name} is due for {$task}.",
            'route' => Route::has('pets.show') ? route('pets.show', ['pet' => $pet]) : url('/'),
            'pet_id' => $pet->getKey(),
            'pet_name' => $pet->name,
            'reminder_id' => $this->reminder->getKey(),
            'reminder_type' => $this->reminder->reminder_type,
            'task' => $task,
            'next_due_on' => Carbon::parse($nextDueOn)->toDateString(),
        ];
    }

    private function label(): string
    {
        if ($this->reminder->reminder_type === PetHealthReminder::TYPE_CUSTOM && filled($this->reminder->custom_text)) {
            return (string) $this->reminder->custom_text;
        }

        return match ($this->reminder->reminder_type) {
            PetHealthReminder::TYPE_VACCINATION => 'vaccination',
            PetHealthReminder::TYPE_FLEA_TREATMENT => 'flea treatment',
            PetHealthReminder::TYPE_WORMING => 'worming',
            PetHealthReminder::TYPE_DENTAL_CHECK => 'a dental check',
            PetHealthReminder::TYPE_VET_CHECKUP => 'a vet checkup',
            PetHealthReminder::TYPE_GROOMING => 'grooming',
            default => 'a health reminder',
        };
    }
}
