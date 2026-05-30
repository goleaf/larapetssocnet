<?php

namespace App\Livewire\Pages\Feed;

use App\Actions\Onboarding\MarkPetReminderShownAction;
use App\Models\Identity\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.livewire-pass-through')]
class Index extends Component
{
    #[Locked]
    public string $source = '';

    #[Locked]
    public string $type = '';

    public bool $showWelcomeBanner = false;

    public bool $showOnboardingPetReminder = false;

    public function mount(): void
    {
        $this->source = $this->sanitizeSource(request()->query('source'));
        $this->type = $this->sanitizeType(request()->query('type'));

        $user = $this->user;
        $onboardingCompletedAt = $user->onboarding_completed_at;

        $this->showWelcomeBanner = $onboardingCompletedAt !== null
            && Carbon::parse((string) $onboardingCompletedAt)->greaterThanOrEqualTo(now()->subDay())
            && ! request()->session()->has('onboarding_welcome_banner_dismissed');

        $this->showOnboardingPetReminder = (bool) $user->onboarding_pet_reminder_pending
            && $user->onboarding_pet_reminder_shown_at === null;

        if ($this->showOnboardingPetReminder) {
            app(MarkPetReminderShownAction::class)->handle($user);
        }
    }

    #[Computed]
    public function user(): User
    {
        $viewer = auth()->user();

        abort_unless($viewer instanceof User, 403);

        return $viewer;
    }

    public function render(): View
    {
        return view('livewire.pages.feed.index');
    }

    private function sanitizeSource(mixed $source): string
    {
        return is_string($source) && in_array($source, ['people', 'pets'], true) ? $source : '';
    }

    private function sanitizeType(mixed $type): string
    {
        return is_string($type) && in_array($type, ['text', 'photo', 'video'], true) ? $type : '';
    }
}
