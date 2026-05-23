<?php

use App\Actions\Engagement\CreateReportAction;
use App\Http\Requests\Moderation\StoreProfileReportRequest;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public int $profileUserId;

    public string $profileDisplayName = '';

    public string $profileUsername = '';

    public ?string $reason = null;

    public ?string $details = null;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;

        $profileUser = $this->profileUser();

        $this->profileDisplayName = $profileUser->display_name ?: $profileUser->name;
        $this->profileUsername = (string) $profileUser->username;
    }

    public function submit(CreateReportAction $createReport): void
    {
        $viewer = $this->viewer();
        $profileUser = $this->profileUser();

        abort_unless($viewer instanceof User, 403);

        try {
            $validated = StoreProfileReportRequest::validateForLivewire($profileUser, $viewer, [
                'reason' => $this->reason,
                'details' => $this->details,
            ]);

            $createReport->handle(
                $viewer,
                $profileUser,
                $validated['reason'],
                $validated['details'] ?? null,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? $exception->getMessage());
            }

            return;
        }

        $this->reset(['reason', 'details']);
        $this->resetValidation();

        $this->js("window.toggleModal('".$this->modalId()."', false)");
        $this->dispatch('profile-toast', message: 'Thanks. Your profile report has been sent to the moderation team.', type: 'success');
    }

    /**
     * @return array<string, string>
     */
    public function reasonOptions(): array
    {
        return Report::profileReasonOptions();
    }

    public function modalId(): string
    {
        return 'profile-report-modal-'.$this->profileUserId;
    }

    private function profileUser(): User
    {
        return User::query()
            ->whereKey($this->profileUserId)
            ->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }
};
?>

<x-ui.modal
 :id="$this->modalId()"
 :name="$this->modalId()"
 title="Report profile"
 :description="'Tell the moderation team what is happening with @'.$profileUsername.'. Reporting does not block this profile.'"
 size="lg"
 :trigger="false"
 data-ui="profile-report-modal">
 <form class="space-y-5" wire:submit="submit">
 <div class="space-y-3">
 <p class="text-sm font-semibold text-bark">Why are you reporting this profile?</p>
 <div class="grid gap-2" data-ui="profile-report-reasons">
 @foreach ($this->reasonOptions() as $value => $label)
 <label
 for="profile-report-reason-{{ $profileUserId }}-{{ $value }}"
 class="flex cursor-pointer items-start gap-3 rounded-[var(--radius-soft)] border border-whisker/40 bg-[color:var(--surface-form)] p-3 transition-colors hover:bg-cream has-[:checked]:border-paw has-[:checked]:bg-paw-light/20">
 <input
 id="profile-report-reason-{{ $profileUserId }}-{{ $value }}"
 type="radio"
 name="profile_report_reason_{{ $profileUserId }}"
 value="{{ $value }}"
 wire:model.live="reason"
 class="mt-1 h-4 w-4 border-whisker text-paw focus:ring-paw"
 @if ($errors->has('reason')) aria-invalid="true" @endif
 >
 <span class="text-sm font-medium text-bark">{{ $label }}</span>
 </label>
 @endforeach
 </div>
 @error('reason')
 <p class="text-sm font-medium text-rose" data-ui="profile-report-reason-error">{{ $message }}</p>
 @enderror
 </div>

 @if ($reason)
 <div data-ui="profile-report-details-field">
 <x-ui.textarea
 id="profile-report-details-{{ $profileUserId }}"
 name="details"
 label="Additional context"
 rows="5"
 maxlength="500"
 hint="Optional. Include only context that helps moderators understand the profile issue."
 :error="$errors->first('details')"
 wire:model.live.debounce.300ms="details"/>
 </div>
 @endif

 <div class="flex flex-col-reverse gap-2 border-t border-whisker/40 pt-4 sm:flex-row sm:justify-end">
 <button
 type="button"
 class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-whisker/40 bg-warm-white px-4 py-2 text-sm font-semibold text-bark transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 @click="window.toggleModal(@js($this->modalId()), false)">
 Cancel
 </button>
 <button
 type="submit"
 data-ui="profile-report-submit"
 class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-paw px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-wait disabled:opacity-70"
 wire:loading.attr="disabled"
 wire:target="submit">
 <span wire:loading.remove wire:target="submit">Submit report</span>
 <span wire:loading wire:target="submit">Submitting...</span>
 </button>
 </div>
 </form>
</x-ui.modal>
